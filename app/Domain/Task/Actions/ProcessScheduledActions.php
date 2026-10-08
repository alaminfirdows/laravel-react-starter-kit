<?php

namespace App\Domain\Task\Actions;

use App\Domain\Activity\Data\Actor;
use App\Domain\Activity\Enums\ActivityChannel;
use App\Domain\Project\Models\Project;
use App\Domain\Task\Enums\ActionStatus;
use App\Domain\Task\Enums\ActionType;
use App\Domain\Task\Enums\Executor;
use App\Domain\Task\Exceptions\InvalidTaskTransition;
use App\Domain\Task\Models\TaskAction;
use App\Domain\Workspace\Contracts\WorkspaceDiscoveryService;
use App\Domain\Workspace\Models\Workspace;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;

/**
 * Time rules, checked by the scheduler:
 * - `wait`: `config.wait.until` (date) or `config.wait.days` after the previous step closed → completes itself.
 * - `scheduled`: `config.schedule.at` (date-time) → an in-app action runs once (as the workspace owner).
 */
class ProcessScheduledActions
{
    public function __construct(
        protected CompleteAction $complete,
        protected RunActionInApp $runInApp,
        protected WorkspaceDiscoveryService $workspaces,
    ) {}

    /**
     * @return array{waits: int, scheduled: int}
     */
    public function handle(): array
    {
        $counts = ['waits' => 0, 'scheduled' => 0];

        TaskAction::query()
            ->whereIn('type', [ActionType::Wait, ActionType::Scheduled])
            ->whereIn('status', [ActionStatus::Pending, ActionStatus::Ready])
            ->lazyById()
            ->each(function (TaskAction $action) use (&$counts): void {
                $workspace = Workspace::query()->whereIn('id', Project::withoutWorkspaceScope()->whereKey($action->project_id)->select('workspace_id'))->first();

                if ($workspace === null) {
                    return;
                }

                $this->workspaces->runAs($workspace, function () use ($action, $workspace, &$counts): void {
                    try {
                        if ($action->type === ActionType::Wait && $this->waitIsOver($action)) {
                            $this->complete->handle($action, Actor::system(ActivityChannel::Cli));
                            $counts['waits']++;
                        } elseif ($action->type === ActionType::Scheduled && $this->scheduleIsDue($action)) {
                            $this->runInApp->handle($action, $workspace->owner()->firstOrFail());
                            $counts['scheduled']++;
                        }
                    } catch (InvalidTaskTransition $e) {
                        Log::info('Scheduled action skipped', ['action' => $action->id, 'reason' => $e->getMessage()]);
                    }
                });
            });

        return $counts;
    }

    private function waitIsOver(TaskAction $action): bool
    {
        $wait = (array) ($action->config['wait'] ?? []);

        if (isset($wait['until'])) {
            return CarbonImmutable::parse((string) $wait['until'])->isPast();
        }

        if (! isset($wait['days'])) {
            return false;
        }

        $previous = $action->task->actions()->where('sort_order', '<', $action->sort_order);

        if ((clone $previous)->where('is_required', true)->whereNotIn('status', [ActionStatus::Done, ActionStatus::Skipped])->exists()) {
            return false;
        }

        $anchor = $previous->max('completed_at') ?? $action->task->started_at ?? $action->created_at;

        return CarbonImmutable::parse($anchor)->addDays((int) $wait['days'])->isPast();
    }

    private function scheduleIsDue(TaskAction $action): bool
    {
        $at = $action->config['schedule']['at'] ?? null;

        if (! is_string($at) || ! in_array($action->executor, [Executor::AppAi, Executor::AppSystem], true)) {
            return false;
        }

        $at = CarbonImmutable::parse($at);

        return $at->isPast() && $action->runs()->where('started_at', '>=', $at)->doesntExist();
    }
}

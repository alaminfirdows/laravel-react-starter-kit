<?php

namespace App\Domain\Task\Actions;

use App\Domain\Activity\Data\Actor;
use App\Domain\Activity\Enums\ActivityChannel;
use App\Domain\Task\Enums\ActionStatus;
use App\Domain\Task\Enums\ActionType;
use App\Domain\Task\Enums\Executor;
use App\Domain\Task\Exceptions\InvalidTaskTransition;
use App\Domain\Task\Models\TaskAction;
use App\Domain\Workspace\Contracts\WorkspaceDiscoveryService;
use App\Domain\Workspace\Models\Workspace;
use App\Domain\Workspace\Scopes\WorkspaceScope;
use App\Models\User;
use Carbon\CarbonImmutable;
use Cron\CronExpression;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Time rules, checked by the scheduler:
 * - `wait`: `config.wait.until` (date) or `config.wait.days` after the previous step closed → completes itself.
 * - `scheduled`: `config.schedule.at` (date-time) → an in-app action runs once (as the workspace owner).
 * - `scheduled` with `config.schedule.cron` (+ optional `timezone`) → runs once per due occurrence; missed occurrences collapse into one run.
 */
class ProcessScheduledActions
{
    public function __construct(
        protected CompleteAction $complete,
        protected ReopenRecurringAction $reopen,
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
            ->where(function (Builder $query): void {
                $query->whereIn('status', [ActionStatus::Pending, ActionStatus::Ready])
                    ->orWhere(fn (Builder $recurring) => $recurring
                        ->where('type', ActionType::Scheduled)
                        ->whereIn('status', [ActionStatus::Done, ActionStatus::Failed])
                        ->whereNotNull('config->schedule->cron'));
            })
            ->with([
                'task' => fn (Relation $task) => $task->withoutGlobalScope(WorkspaceScope::class),
                'task.project' => fn (Relation $project) => $project->withoutGlobalScope(WorkspaceScope::class),
                'task.project.workspace.owner',
            ])
            ->withMax('runs as last_run_started_at', 'started_at')
            ->lazyById()
            ->each(function (TaskAction $action) use (&$counts): void {
                $workspace = data_get($action, 'task.project.workspace');

                if (! $workspace instanceof Workspace || ! $workspace->isActive()) {
                    return;
                }

                $this->workspaces->runAs($workspace, function () use ($action, $workspace, &$counts): void {
                    try {
                        if ($action->type === ActionType::Wait && $this->waitIsOver($action)) {
                            $this->complete->handle($action, Actor::system(ActivityChannel::Cli));
                            $counts['waits']++;
                        } elseif ($action->type === ActionType::Scheduled && $this->scheduleIsDue($action)) {
                            $owner = $workspace->owner ?? throw (new ModelNotFoundException)->setModel(User::class);
                            DB::transaction(function () use ($action, $owner): void {
                                $this->reopen->handle($action, Actor::system(ActivityChannel::Cli));
                                $this->runInApp->handle($action, $owner);
                            });
                            $counts['scheduled']++;
                        }
                    } catch (InvalidTaskTransition $e) {
                        Log::info('Scheduled action skipped', ['action' => $action->id, 'reason' => $e->getMessage()]);
                    } catch (Throwable $e) {
                        Log::error('Scheduled action failed', ['action' => $action->id, 'exception' => $e]);
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
        if (! in_array($action->executor, [Executor::AppAi, Executor::AppSystem], true)) {
            return false;
        }

        $cron = $action->config['schedule']['cron'] ?? null;

        if ($cron !== null) {
            return $this->cronIsDue($action, $cron);
        }

        $at = $action->config['schedule']['at'] ?? null;

        if (! is_string($at)) {
            return false;
        }

        $at = CarbonImmutable::parse($at);

        $lastRun = $action->getAttribute('last_run_started_at');

        return $at->isPast() && ($lastRun === null || CarbonImmutable::parse($lastRun)->lessThan($at));
    }

    private function cronIsDue(TaskAction $action, mixed $cron): bool
    {
        if (! is_string($cron) || ! CronExpression::isValidExpression($cron)) {
            Log::info('Scheduled action skipped', ['action' => $action->id, 'reason' => 'invalid cron expression']);

            return false;
        }

        $timezone = $action->config['schedule']['timezone'] ?? config('app.timezone');

        if (! is_string($timezone) || ! in_array($timezone, timezone_identifiers_list(), true)) {
            Log::info('Scheduled action skipped', ['action' => $action->id, 'reason' => 'invalid timezone']);

            return false;
        }

        $lastRun = $action->getAttribute('last_run_started_at');
        $anchor = CarbonImmutable::parse($lastRun ?? $action->created_at);

        try {
            $next = new CronExpression($cron)->getNextRunDate($anchor, 0, false, $timezone);
        } catch (RuntimeException) {
            Log::info('Scheduled action skipped', ['action' => $action->id, 'reason' => 'invalid cron expression']);

            return false;
        }

        return CarbonImmutable::instance($next)->lessThanOrEqualTo(now());
    }
}

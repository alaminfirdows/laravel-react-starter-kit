<?php

namespace App\Checks\Jobs;

use App\Checks\CheckRegistry;
use App\Checks\CheckResult;
use App\Domain\Activity\Data\Actor;
use App\Domain\Task\Actions\AttachEvidence;
use App\Domain\Task\Actions\CompleteAction;
use App\Domain\Task\Actions\FinishRun;
use App\Domain\Task\Actions\SaveRunOutput;
use App\Domain\Task\Concerns\ResolvesStartedRun;
use App\Domain\Task\Data\CriterionData;
use App\Domain\Task\Data\EvidenceData;
use App\Domain\Task\Enums\CriterionKind;
use App\Domain\Task\Enums\EvidenceKind;
use App\Domain\Task\Enums\RunStatus;
use App\Domain\Task\Exceptions\InvalidActionTransition;
use App\Domain\Task\Models\ActionRun;
use App\Domain\Task\Models\TaskAction;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Runs `config.check` against `config.url` or the project website and writes `check_result` evidence.
 * Passed → completes the action; failed → run failed with the reason, action back to ready.
 */
class RunCheckJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable, ResolvesStartedRun;

    public int $tries = 1;

    public int $timeout = 60;

    public int $uniqueFor = 60;

    public function __construct(public string $runId, public string $userId) {}

    public function uniqueId(): string
    {
        return ActionRun::query()->whereKey($this->runId)->value('task_action_id') ?? $this->runId;
    }

    public function handle(CheckRegistry $registry, AttachEvidence $attach, SaveRunOutput $saveOutput, CompleteAction $complete, FinishRun $finish): void
    {
        $this->withStartedRun($this->runId, $this->userId, function (ActionRun $run) use ($registry, $attach, $saveOutput, $complete, $finish): void {
            $actor = Actor::system();
            $action = $run->action;
            $key = (string) ($action->config['check'] ?? '');
            $check = $registry->find($key);

            if ($check === null) {
                $finish->handle($run, $actor, RunStatus::Failed, __('Unknown check [:key].', ['key' => $key]));

                return;
            }

            $url = (string) ($action->config['url'] ?? $action->task->project->website_url ?? '');
            $result = $url === '' ? CheckResult::fail(__('Add the project website first.')) : $check->run($url);

            $attach->handle($action, new EvidenceData(
                EvidenceKind::CheckResult,
                $check->label(),
                $result->reason,
                $this->criterionKey($action, $key),
                passed: $result->passed,
            ), $actor);
            $saveOutput->handle($action, $actor, ($result->passed ? '✅ ' : '❌ ').$result->reason, ['check' => $key, 'url' => $url, 'passed' => $result->passed]);

            if (! $result->passed) {
                $finish->handle($run->refresh(), $actor, RunStatus::Failed, $result->reason);

                return;
            }

            try {
                $complete->handle($action->refresh(), $actor);
            } catch (InvalidActionTransition) {
                $finish->handle($run->refresh(), $actor, RunStatus::Succeeded);
            }
        });
    }

    /**
     * Members see a generic reason; the queue worker reports the exception itself.
     */
    public function failed(?Throwable $exception): void
    {
        $this->withStartedRun($this->runId, $this->userId, function (ActionRun $run): void {
            app(FinishRun::class)->handle($run, Actor::system(), RunStatus::Failed, __('The check failed.'));
        });
    }

    /**
     * The check criterion this result proves: matching `check_ref`, else matching key.
     */
    private function criterionKey(TaskAction $action, string $checkKey): ?string
    {
        $criteria = array_map(CriterionData::fromArray(...), $action->task->completion_criteria ?? []);

        foreach ($criteria as $criterion) {
            if ($criterion->kind === CriterionKind::Check && ($criterion->checkRef ?? $criterion->key) === $checkKey) {
                return $criterion->key;
            }
        }

        return null;
    }
}

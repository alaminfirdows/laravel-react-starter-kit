<?php

namespace App\Domain\Task\Actions;

use App\Ai\Jobs\RunAppAiActionJob;
use App\Ai\Support\UsageMeter;
use App\Checks\Jobs\RunCheckJob;
use App\Domain\Activity\Data\Actor;
use App\Domain\Prompt\Actions\RenderFullPrompt;
use App\Domain\Task\Enums\ActionStatus;
use App\Domain\Task\Enums\Executor;
use App\Domain\Task\Enums\RunChannel;
use App\Domain\Task\Exceptions\InvalidActionTransition;
use App\Domain\Task\Models\ActionRun;
use App\Domain\Task\Models\TaskAction;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * "Run in app": `app_ai` actions go to an agent (after the budget check), `app_system` actions to a machine check.
 * One run at a time per action (row lock + running check).
 */
class RunActionInApp
{
    public function __construct(
        protected StartAction $start,
        protected RenderFullPrompt $render,
        protected UsageMeter $usage,
    ) {}

    public function handle(TaskAction $action, User $user): ActionRun
    {
        $isAi = $action->executor === Executor::AppAi;

        if (! $isAi && $action->executor !== Executor::AppSystem) {
            throw InvalidActionTransition::notInApp($action);
        }

        if ($isAi) {
            $this->usage->ensureWithinBudget($action->task->project->workspace);
        }

        $run = DB::transaction(function () use ($action, $user, $isAi): ActionRun {
            $locked = TaskAction::query()->whereKey($action->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === ActionStatus::Running) {
                throw InvalidActionTransition::alreadyRunning($locked);
            }

            return $isAi
                ? $this->start->handle($locked, Actor::appAi($user), RunChannel::AppAi, $this->render->handle($locked, withProtocol: false))
                : $this->start->handle($locked, Actor::user($user), RunChannel::System);
        });

        ($isAi ? RunAppAiActionJob::dispatch($run->id, $user->id) : RunCheckJob::dispatch($run->id, $user->id))->afterCommit();

        return $run;
    }
}

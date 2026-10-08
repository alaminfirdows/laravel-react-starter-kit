<?php

namespace App\Domain\Task\Actions;

use App\Ai\Jobs\RunAppAiActionJob;
use App\Ai\Support\UsageMeter;
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
 * "Run with AI": checks budget, starts one run under a row lock and queues the agent.
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
        if ($action->executor !== Executor::AppAi) {
            throw InvalidActionTransition::notAppAi($action);
        }

        $this->usage->ensureWithinBudget($action->task->project->workspace);

        $run = DB::transaction(function () use ($action, $user): ActionRun {
            $locked = TaskAction::query()->whereKey($action->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === ActionStatus::Running) {
                throw InvalidActionTransition::alreadyRunning($locked);
            }

            return $this->start->handle($locked, Actor::appAi($user), RunChannel::AppAi, $this->render->handle($locked, withProtocol: false));
        });

        RunAppAiActionJob::dispatch($run->id, $user->id)->afterCommit();

        return $run;
    }
}

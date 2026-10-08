<?php

namespace App\Domain\Task\Actions;

use App\Domain\Activity\ActivityRecorder;
use App\Domain\Activity\Data\Actor;
use App\Domain\Task\Enums\ActionStatus;
use App\Domain\Task\Enums\RunChannel;
use App\Domain\Task\Enums\RunStatus;
use App\Domain\Task\Enums\TaskStatus;
use App\Domain\Task\Exceptions\InvalidActionTransition;
use App\Domain\Task\Models\ActionRun;
use App\Domain\Task\Models\TaskAction;
use Illuminate\Support\Facades\DB;

class StartAction
{
    public function __construct(
        protected SyncTaskFromActions $sync,
        protected ActivityRecorder $activity,
    ) {}

    public function handle(TaskAction $action, Actor $actor, RunChannel $channel, ?string $renderedPrompt = null): ActionRun
    {
        if ($action->status->isClosed()) {
            throw InvalidActionTransition::closed($action);
        }

        if ($action->task->status === TaskStatus::Locked) {
            throw InvalidActionTransition::taskLocked($action);
        }

        if ($action->status === ActionStatus::AwaitingApproval) {
            throw InvalidActionTransition::approvalRequired($action);
        }

        return DB::transaction(function () use ($action, $actor, $channel, $renderedPrompt): ActionRun {
            $run = ActionRun::create([
                'task_action_id' => $action->id,
                'task_id' => $action->task_id,
                'project_id' => $action->project_id,
                'channel' => $channel,
                'actor_type' => $actor->type,
                'actor_id' => $actor->id,
                'client_name' => $actor->clientName,
                'rendered_prompt' => $renderedPrompt,
                'status' => RunStatus::Started,
                'started_at' => now(),
            ]);

            $action->forceFill(['status' => ActionStatus::Running, 'last_run_id' => $run->id])->save();

            $this->activity->record('action.started', $action, ['run_id' => $run->id, 'channel' => $channel->value], $actor);
            $this->sync->handle($action->task, $actor);

            return $run;
        });
    }
}

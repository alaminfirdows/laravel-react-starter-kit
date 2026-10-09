<?php

namespace App\Domain\Task\Actions;

use App\Domain\Activity\ActivityRecorder;
use App\Domain\Activity\Data\Actor;
use App\Domain\Task\Enums\ActionStatus;
use App\Domain\Task\Enums\RunStatus;
use App\Domain\Task\Enums\TaskStatus;
use App\Domain\Task\Exceptions\InvalidActionTransition;
use App\Domain\Task\Models\TaskAction;
use Illuminate\Support\Facades\DB;

class SkipAction
{
    public function __construct(
        protected CloseStartedRuns $closeRuns,
        protected SyncTaskFromActions $sync,
        protected ActivityRecorder $activity,
    ) {}

    public function handle(TaskAction $action, Actor $actor, ?string $reason = null): TaskAction
    {
        if ($action->status->isClosed()) {
            return $action;
        }

        if ($action->task->status === TaskStatus::Locked) {
            throw InvalidActionTransition::taskLocked($action);
        }

        return DB::transaction(function () use ($action, $actor, $reason): TaskAction {
            $this->closeRuns->handle($action, RunStatus::Cancelled, $actor);

            $action->forceFill([
                'status' => ActionStatus::Skipped,
                'completed_at' => now(),
                'completed_by_type' => $actor->type->value,
                'completed_by_id' => $actor->id,
            ])->save();

            $this->activity->record('action.skipped', $action, array_filter(['reason' => $reason]), $actor);
            $this->sync->handle($action->task, $actor);

            return $action;
        });
    }
}

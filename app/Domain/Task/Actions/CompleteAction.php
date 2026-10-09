<?php

namespace App\Domain\Task\Actions;

use App\Domain\Activity\ActivityRecorder;
use App\Domain\Activity\Data\Actor;
use App\Domain\Task\Enums\ActionStatus;
use App\Domain\Task\Enums\ApprovalStatus;
use App\Domain\Task\Enums\RunStatus;
use App\Domain\Task\Enums\TaskStatus;
use App\Domain\Task\Exceptions\InvalidActionTransition;
use App\Domain\Task\Models\TaskAction;
use Illuminate\Support\Facades\DB;

/**
 * The only path to `done` for an action: approval gate (§F.4), criteria (§F.2), then task sync (§F.3).
 * Checks run on the locked row, so two concurrent completes cannot both pass.
 */
class CompleteAction
{
    public function __construct(
        protected EvaluateCriteria $criteria,
        protected CloseStartedRuns $closeRuns,
        protected SyncTaskFromActions $sync,
        protected ActivityRecorder $activity,
    ) {}

    public function handle(TaskAction $action, Actor $actor, ?string $outputMd = null): TaskAction
    {
        return DB::transaction(function () use ($action, $actor, $outputMd): TaskAction {
            $action->newQueryWithoutScopes()->whereKey($action->getKey())->lockForUpdate()->value('id');
            $action->refresh();

            if ($action->status->isClosed()) {
                return $action;
            }

            if ($action->task->status === TaskStatus::Locked) {
                throw InvalidActionTransition::taskLocked($action);
            }

            if ($action->requires_approval && $action->approvals()->where('status', ApprovalStatus::Approved)->doesntExist()) {
                throw InvalidActionTransition::approvalRequired($action);
            }

            $unmet = $this->criteria->handle($action);

            if ($unmet !== []) {
                throw InvalidActionTransition::unmetCriteria($action, $unmet);
            }

            if ($outputMd !== null && $action->lastRun !== null) {
                $action->lastRun->update(['output_md' => $outputMd]);
            }

            $this->closeRuns->handle($action, RunStatus::Succeeded, $actor);

            $from = $action->status;
            $action->forceFill([
                'status' => ActionStatus::Done,
                'completed_at' => now(),
                'completed_by_type' => $actor->type->value,
                'completed_by_id' => $actor->id,
            ])->save();

            $this->activity->record('action.completed', $action, ['from' => $from->value], $actor);
            $this->sync->handle($action->task, $actor);

            return $action;
        });
    }
}

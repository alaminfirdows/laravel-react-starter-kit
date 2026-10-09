<?php

namespace App\Domain\Task\Actions;

use App\Domain\Activity\ActivityRecorder;
use App\Domain\Activity\Data\Actor;
use App\Domain\Task\Enums\ActionStatus;
use App\Domain\Task\Exceptions\InvalidActionTransition;
use App\Domain\Task\Models\Task;
use App\Domain\Task\Models\TaskAction;
use Illuminate\Support\Facades\DB;

/**
 * Stops a recurring (cron) action between occurrences: Done/Failed → Skipped.
 * The scheduler only picks up Done/Failed recurring actions, so a skipped one never runs again.
 */
class StopRecurringSchedule
{
    public function __construct(
        protected SyncTaskFromActions $sync,
        protected ActivityRecorder $activity,
    ) {}

    /**
     * @throws InvalidActionTransition when the action is not a Done/Failed recurring action
     */
    public function handle(TaskAction $action, Actor $actor): TaskAction
    {
        return DB::transaction(function () use ($action, $actor): TaskAction {
            $action->newQueryWithoutScopes()->whereKey($action->getKey())->lockForUpdate()->value('id');
            Task::withoutGlobalScopes()->whereKey($action->task_id)->lockForUpdate()->value('id');
            $action->refresh();

            if (! $action->isRecurring() || ! in_array($action->status, [ActionStatus::Done, ActionStatus::Failed], true)) {
                throw InvalidActionTransition::scheduleNotStoppable($action);
            }

            $from = $action->status;
            $action->forceFill([
                'status' => ActionStatus::Skipped,
                'completed_at' => $action->completed_at ?? now(),
                'completed_by_type' => $actor->type->value,
                'completed_by_id' => $actor->id,
            ])->save();

            $this->activity->record('action.schedule_stopped', $action, ['from' => $from->value], $actor);
            $this->sync->handle($action->task, $actor);

            return $action;
        });
    }
}

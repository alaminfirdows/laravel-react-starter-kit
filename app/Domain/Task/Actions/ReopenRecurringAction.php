<?php

namespace App\Domain\Task\Actions;

use App\Domain\Activity\ActivityRecorder;
use App\Domain\Activity\Data\Actor;
use App\Domain\Task\Enums\ActionStatus;
use App\Domain\Task\Models\Task;
use App\Domain\Task\Models\TaskAction;
use Illuminate\Support\Facades\DB;

/**
 * Makes a finished or failed recurring (cron) action ready for its next occurrence.
 * Locks the action, then its task (same order as LockActionForChange), reloads, and re-checks the status.
 */
class ReopenRecurringAction
{
    public function __construct(protected ActivityRecorder $activity) {}

    public function handle(TaskAction $action, Actor $actor): TaskAction
    {
        return DB::transaction(function () use ($action, $actor): TaskAction {
            $action->newQueryWithoutScopes()->whereKey($action->getKey())->lockForUpdate()->value('id');
            Task::withoutGlobalScopes()->whereKey($action->task_id)->lockForUpdate()->value('id');
            $action->refresh();

            if (! in_array($action->status, [ActionStatus::Done, ActionStatus::Failed], true)) {
                return $action;
            }

            $from = $action->status;
            $action->forceFill([
                'status' => ActionStatus::Ready,
                'completed_at' => null,
                'completed_by_type' => null,
                'completed_by_id' => null,
            ])->save();

            $this->activity->record('action.reopened', $action, ['from' => $from->value, 'reason' => 'recurring'], $actor);

            return $action;
        });
    }
}

<?php

namespace App\Domain\Task\Actions;

use App\Domain\Activity\ActivityRecorder;
use App\Domain\Activity\Data\Actor;
use App\Domain\Task\Enums\ActionStatus;
use App\Domain\Task\Models\TaskAction;
use Illuminate\Support\Facades\DB;

/**
 * Makes a finished recurring (cron) action ready for its next occurrence.
 */
class ReopenRecurringAction
{
    public function __construct(protected ActivityRecorder $activity) {}

    public function handle(TaskAction $action, Actor $actor): TaskAction
    {
        if ($action->status !== ActionStatus::Done) {
            return $action;
        }

        return DB::transaction(function () use ($action, $actor): TaskAction {
            $action->forceFill([
                'status' => ActionStatus::Ready,
                'completed_at' => null,
                'completed_by_type' => null,
                'completed_by_id' => null,
            ])->save();

            $this->activity->record('action.reopened', $action, ['reason' => 'recurring'], $actor);

            return $action;
        });
    }
}

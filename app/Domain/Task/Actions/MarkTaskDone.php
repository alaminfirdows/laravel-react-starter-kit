<?php

namespace App\Domain\Task\Actions;

use App\Domain\Activity\ActivityRecorder;
use App\Domain\Activity\Data\Actor;
use App\Domain\Task\Enums\ActionStatus;
use App\Domain\Task\Enums\TaskStatus;
use App\Domain\Task\Enums\Verification;
use App\Domain\Task\Exceptions\InvalidTaskTransition;
use App\Domain\Task\Models\Task;
use Illuminate\Support\Facades\DB;

class MarkTaskDone
{
    public function __construct(
        protected RollupTaskStatus $rollup,
        protected RefreshTaskLocks $refreshLocks,
        protected ActivityRecorder $activity,
    ) {}

    public function handle(Task $task, Actor $actor): Task
    {
        if ($task->status->isClosed()) {
            return $task;
        }

        if (! $task->isLeaf()) {
            throw InvalidTaskTransition::notLeaf($task);
        }

        if ($task->status === TaskStatus::Locked) {
            throw InvalidTaskTransition::locked($task);
        }

        return DB::transaction(function () use ($task, $actor): Task {
            $completed = [
                'completed_at' => now(),
                'completed_by_type' => $actor->type->value,
                'completed_by_id' => $actor->id,
            ];

            $task->actions()
                ->whereNotIn('status', [ActionStatus::Done, ActionStatus::Skipped])
                ->update(['status' => ActionStatus::Done, ...$completed, 'updated_at' => now()]);

            $from = $task->status;
            $task->forceFill([
                'status' => TaskStatus::Done,
                'progress_pct' => 100,
                'verification' => Verification::SelfReported,
                'started_at' => $task->started_at ?? now(),
                ...$completed,
            ])->save();

            $this->activity->record('task.completed', $task, ['from' => $from->value], $actor);
            $this->rollup->handle($task, $actor);
            $this->refreshLocks->handle($task->project);

            return $task;
        });
    }
}

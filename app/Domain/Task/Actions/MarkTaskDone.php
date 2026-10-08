<?php

namespace App\Domain\Task\Actions;

use App\Domain\Activity\Data\Actor;
use App\Domain\Task\Enums\TaskStatus;
use App\Domain\Task\Exceptions\InvalidTaskTransition;
use App\Domain\Task\Models\Task;
use Illuminate\Support\Facades\DB;

/**
 * Founder ticks a leaf: completes its open actions through `CompleteAction` (so criteria and
 * approvals still hold), then closes the task if it has no gating actions.
 */
class MarkTaskDone
{
    public function __construct(
        protected CompleteAction $completeAction,
        protected SyncTaskFromActions $sync,
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
            foreach ($task->actions()->get() as $action) {
                $this->completeAction->handle($action->setRelation('task', $task), $actor);
            }

            $task->refresh();

            if (! $task->status->isClosed()) {
                $this->sync->close($task, $actor);
            }

            return $task;
        });
    }
}

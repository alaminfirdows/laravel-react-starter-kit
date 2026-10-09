<?php

namespace App\Domain\Task\Actions;

use App\Domain\Activity\ActivityRecorder;
use App\Domain\Activity\Data\Actor;
use App\Domain\Task\Exceptions\InvalidTaskTransition;
use App\Domain\Task\Models\Task;
use App\Models\User;

/**
 * Sets or clears the task assignee. The assignee must be able to edit the project.
 */
class AssignTask
{
    public function __construct(protected ActivityRecorder $activity) {}

    public function handle(Task $task, ?User $assignee, Actor $actor): Task
    {
        if ($task->assignee_id === $assignee?->id) {
            return $task;
        }

        if ($assignee !== null && ! $task->loadMissing('workspace')->workspace->editors()->whereKey($assignee->id)->exists()) {
            throw InvalidTaskTransition::assigneeCannotEdit();
        }

        $previous = $task->assignee_id;
        $task->forceFill(['assignee_id' => $assignee?->id])->save();

        $this->activity->record($assignee ? 'task.assigned' : 'task.unassigned', $task, [
            'before' => ['assignee_id' => $previous],
            'after' => ['assignee_id' => $assignee?->id],
        ], $actor);

        return $task;
    }
}

<?php

namespace App\Domain\Task\Actions;

use App\Domain\Task\Enums\TaskStatus;
use App\Domain\Task\Exceptions\InvalidActionTransition;
use App\Domain\Task\Models\Task;
use App\Domain\Task\Models\TaskAction;

/**
 * First step of every action status change, inside its transaction: lock the action row,
 * then its task row (same order as the task sync path), reload, and check the shared rules.
 * A concurrent start, skip or complete waits here and then sees the committed status.
 */
class LockActionForChange
{
    /**
     * @return bool false when the action is already closed
     *
     * @throws InvalidActionTransition when the task is locked
     */
    public function handle(TaskAction $action): bool
    {
        $action->newQueryWithoutScopes()->whereKey($action->getKey())->lockForUpdate()->value('id');
        Task::withoutGlobalScopes()->whereKey($action->task_id)->lockForUpdate()->value('id');
        $action->refresh();

        if ($action->status->isClosed()) {
            return false;
        }

        if ($action->task->status === TaskStatus::Locked) {
            throw InvalidActionTransition::taskLocked($action);
        }

        return true;
    }
}

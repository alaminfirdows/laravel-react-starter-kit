<?php

namespace App\Domain\Task\Policies;

use App\Domain\Task\Models\Task;
use App\Models\User;

class TaskPolicy
{
    public function view(User $user, Task $task): bool
    {
        return $user->can('view', $task->project);
    }

    public function update(User $user, Task $task): bool
    {
        return $user->can('update', $task->project);
    }
}

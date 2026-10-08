<?php

namespace App\Domain\Task\Exceptions;

use App\Domain\Task\Models\Task;
use DomainException;

class InvalidTaskTransition extends DomainException
{
    public static function notLeaf(Task $task): self
    {
        return new self(__('Complete the subtasks of ":title" instead.', ['title' => $task->title]));
    }

    public static function locked(Task $task): self
    {
        return new self(__('":title" is locked until its dependencies are done.', ['title' => $task->title]));
    }
}

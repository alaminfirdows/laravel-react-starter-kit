<?php

namespace App\Domain\Task\Enums;

use App\Support\Enums\HasOptions;

enum TaskStatus: string
{
    use HasOptions;

    case Locked = 'locked';
    case Todo = 'todo';
    case InProgress = 'in_progress';
    case Blocked = 'blocked';
    case AwaitingApproval = 'awaiting_approval';
    case Done = 'done';
    case Skipped = 'skipped';

    public function isClosed(): bool
    {
        return $this === self::Done || $this === self::Skipped;
    }
}

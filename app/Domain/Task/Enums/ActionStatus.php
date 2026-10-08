<?php

namespace App\Domain\Task\Enums;

use App\Support\Enums\HasOptions;

enum ActionStatus: string
{
    use HasOptions;

    case Pending = 'pending';
    case Ready = 'ready';
    case Running = 'running';
    case AwaitingInput = 'awaiting_input';
    case AwaitingApproval = 'awaiting_approval';
    case Done = 'done';
    case Failed = 'failed';
    case Skipped = 'skipped';

    public function isClosed(): bool
    {
        return $this === self::Done || $this === self::Skipped;
    }
}

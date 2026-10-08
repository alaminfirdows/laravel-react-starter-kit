<?php

namespace App\Domain\Task\Enums;

enum RunStatus: string
{
    case Started = 'started';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Cancelled = 'cancelled';

    public function isFinished(): bool
    {
        return $this !== self::Started;
    }
}

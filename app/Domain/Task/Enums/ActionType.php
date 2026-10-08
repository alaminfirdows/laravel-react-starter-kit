<?php

namespace App\Domain\Task\Enums;

use App\Support\Enums\HasOptions;

enum ActionType: string
{
    use HasOptions;

    case Ai = 'ai';
    case Research = 'research';
    case Browser = 'browser';
    case Document = 'document';
    case File = 'file';
    case Mcp = 'mcp';
    case Check = 'check';
    case Input = 'input';
    case Approval = 'approval';
    case Manual = 'manual';
    case Wait = 'wait';
    case Scheduled = 'scheduled';
}

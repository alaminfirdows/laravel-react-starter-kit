<?php

namespace App\Domain\Activity\Enums;

enum ActivityChannel: string
{
    case Web = 'web';
    case Mcp = 'mcp';
    case Queue = 'queue';
    case Cli = 'cli';
}

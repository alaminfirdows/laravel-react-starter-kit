<?php

namespace App\Domain\Knowledge\Enums;

enum DocSource: string
{
    case User = 'user';
    case ClaudeMcp = 'claude_mcp';
    case AppAi = 'app_ai';
    case Upload = 'upload';
    case Import = 'import';
}

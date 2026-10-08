<?php

namespace App\Domain\Task\Enums;

use App\Support\Enums\HasOptions;

enum Executor: string
{
    use HasOptions;

    case ClaudeDesktop = 'claude_desktop';
    case ClaudeChrome = 'claude_chrome';
    case AppAi = 'app_ai';
    case AppSystem = 'app_system';
    case User = 'user';
}

<?php

namespace App\Domain\Task\Enums;

enum RunChannel: string
{
    case CopyPrompt = 'copy_prompt';
    case DeepLink = 'deep_link';
    case Mcp = 'mcp';
    case AppAi = 'app_ai';
    case Manual = 'manual';
    case System = 'system';
}

<?php

namespace App\Domain\Prompt\Enums;

use App\Domain\Task\Enums\ActionType;
use App\Domain\Task\Enums\Executor;
use App\Domain\Task\Models\TaskAction;
use App\Support\Enums\HasOptions;

enum DeepLinkTarget: string
{
    use HasOptions;

    case Chat = 'chat';
    case Cowork = 'cowork';

    public static function for(TaskAction $action): self
    {
        return in_array($action->type, [ActionType::File, ActionType::Browser], true) || $action->executor === Executor::ClaudeChrome
            ? self::Cowork
            : self::Chat;
    }

    public function baseUrl(): string
    {
        return match ($this) {
            self::Chat => 'claude://claude.ai/new?q=',
            self::Cowork => 'claude://cowork/new?q=',
        };
    }
}

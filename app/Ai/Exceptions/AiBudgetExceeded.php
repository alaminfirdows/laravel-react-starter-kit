<?php

namespace App\Ai\Exceptions;

use App\Domain\Task\Exceptions\InvalidTaskTransition;
use App\Domain\Workspace\Models\Workspace;

class AiBudgetExceeded extends InvalidTaskTransition
{
    public static function for(Workspace $workspace, int $budget): self
    {
        return new self(__('":name" used its AI budget of :tokens tokens this month.', [
            'name' => $workspace->name,
            'tokens' => number_format($budget),
        ]));
    }
}

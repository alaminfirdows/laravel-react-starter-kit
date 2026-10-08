<?php

namespace App\Domain\Project\Enums;

use App\Support\Enums\HasOptions;

enum LegalEntityStatus: string
{
    use HasOptions;

    case None = 'none';
    case InProgress = 'in_progress';
    case Registered = 'registered';
}

<?php

namespace App\Domain\Project\Enums;

use App\Support\Enums\HasOptions;

enum ProjectStatus: string
{
    use HasOptions;

    case Draft = 'draft';
    case Active = 'active';
    case Archived = 'archived';
}

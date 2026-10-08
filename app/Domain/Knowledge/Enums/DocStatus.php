<?php

namespace App\Domain\Knowledge\Enums;

use App\Support\Enums\HasOptions;

enum DocStatus: string
{
    use HasOptions;

    case Draft = 'draft';
    case Approved = 'approved';
    case Archived = 'archived';
}

<?php

namespace App\Domain\Catalog\Enums;

use App\Support\Enums\HasOptions;

enum CatalogStatus: string
{
    use HasOptions;

    case Draft = 'draft';
    case Published = 'published';
    case Archived = 'archived';
}

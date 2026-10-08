<?php

namespace App\Domain\Catalog\Enums;

use App\Support\Enums\HasOptions;

enum CatalogPhase: string
{
    use HasOptions;

    case PrePlanning = 'pre_planning';
    case Research = 'research';
    case Foundation = 'foundation';
    case Product = 'product';
    case Launch = 'launch';
    case Growth = 'growth';
    case Operations = 'operations';
}

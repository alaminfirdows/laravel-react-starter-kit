<?php

namespace App\Domain\Catalog\Enums;

enum CatalogStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Archived = 'archived';
}

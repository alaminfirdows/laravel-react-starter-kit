<?php

namespace App\Domain\Catalog\Enums;

use App\Support\Enums\HasOptions;

enum PackVisibility: string
{
    use HasOptions;

    case Private = 'private';
    case Public = 'public';
}

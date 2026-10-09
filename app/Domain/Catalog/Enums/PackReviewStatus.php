<?php

namespace App\Domain\Catalog\Enums;

use App\Support\Enums\HasOptions;

enum PackReviewStatus: string
{
    use HasOptions;

    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
}

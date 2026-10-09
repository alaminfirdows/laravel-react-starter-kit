<?php

namespace App\Domain\Task\Enums;

use App\Support\Enums\HasOptions;

enum TaskPriority: string
{
    use HasOptions;

    case P0 = 'p0';
    case P1 = 'p1';
    case P2 = 'p2';
    case P3 = 'p3';
}

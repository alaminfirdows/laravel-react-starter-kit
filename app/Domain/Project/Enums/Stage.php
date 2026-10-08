<?php

namespace App\Domain\Project\Enums;

use App\Support\Enums\HasOptions;

enum Stage: string
{
    use HasOptions;

    case Idea = 'idea';
    case Validating = 'validating';
    case Building = 'building';
    case PreLaunch = 'pre_launch';
    case Launched = 'launched';
    case Revenue = 'revenue';
    case Scaling = 'scaling';
}

<?php

namespace App\Domain\Task\Enums;

enum CriterionKind: string
{
    case Manual = 'manual';
    case Evidence = 'evidence';
    case Check = 'check';
}

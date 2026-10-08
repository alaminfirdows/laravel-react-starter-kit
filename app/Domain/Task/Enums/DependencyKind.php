<?php

namespace App\Domain\Task\Enums;

enum DependencyKind: string
{
    case Hard = 'hard';
    case Soft = 'soft';
}

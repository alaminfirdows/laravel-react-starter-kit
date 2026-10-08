<?php

namespace App\Domain\Activity\Enums;

enum ActorType: string
{
    case User = 'user';
    case Agent = 'agent';
    case System = 'system';
}

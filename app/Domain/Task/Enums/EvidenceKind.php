<?php

namespace App\Domain\Task\Enums;

enum EvidenceKind: string
{
    case Url = 'url';
    case Value = 'value';
    case File = 'file';
    case Screenshot = 'screenshot';
    case CheckResult = 'check_result';
    case Note = 'note';
}

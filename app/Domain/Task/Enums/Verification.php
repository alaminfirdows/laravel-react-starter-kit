<?php

namespace App\Domain\Task\Enums;

enum Verification: string
{
    case None = 'none';
    case SelfReported = 'self_reported';
    case EvidenceAttached = 'evidence_attached';
    case Verified = 'verified';
}

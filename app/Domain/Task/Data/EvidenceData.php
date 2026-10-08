<?php

namespace App\Domain\Task\Data;

use App\Domain\Task\Enums\EvidenceKind;

final readonly class EvidenceData
{
    public function __construct(
        public EvidenceKind $kind,
        public string $label,
        public ?string $value = null,
        public ?string $criterionKey = null,
        public ?string $mediaId = null,
        public ?bool $passed = null,
    ) {}
}

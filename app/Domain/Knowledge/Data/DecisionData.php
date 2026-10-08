<?php

namespace App\Domain\Knowledge\Data;

use App\Domain\Knowledge\Enums\DocSource;
use Carbon\CarbonImmutable;

final readonly class DecisionData
{
    /**
     * @param  list<string>|null  $alternatives
     */
    public function __construct(
        public string $title,
        public string $decisionMd,
        public ?string $rationaleMd = null,
        public ?array $alternatives = null,
        public ?CarbonImmutable $decidedOn = null,
        public ?CarbonImmutable $revisitOn = null,
        public ?string $taskId = null,
        public DocSource $source = DocSource::User,
    ) {}
}

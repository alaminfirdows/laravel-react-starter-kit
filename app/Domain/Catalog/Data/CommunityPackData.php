<?php

namespace App\Domain\Catalog\Data;

use App\Domain\Catalog\Enums\PackVisibility;
use App\Domain\Project\Enums\ProjectPhase;

final readonly class CommunityPackData
{
    /**
     * @param  list<int>  $catalogTaskIds  root catalog tasks, in order
     */
    public function __construct(
        public string $name,
        public ProjectPhase $phase,
        public PackVisibility $visibility,
        public array $catalogTaskIds,
        public ?string $descriptionMd = null,
    ) {}
}

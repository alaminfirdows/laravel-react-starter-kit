<?php

namespace App\Domain\Catalog\Data;

use App\Domain\Catalog\Enums\CatalogStatus;
use App\Domain\Project\Enums\ProjectPhase;

final readonly class PackData
{
    /**
     * @param  list<array{catalog_task_id: int, include_subtree: bool}>  $items  in order
     */
    public function __construct(
        public string $key,
        public string $name,
        public ProjectPhase $phase,
        public array $items,
        public ?string $descriptionMd = null,
        public bool $isDefault = false,
        public CatalogStatus $status = CatalogStatus::Published,
    ) {}
}

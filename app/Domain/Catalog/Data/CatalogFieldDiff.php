<?php

namespace App\Domain\Catalog\Data;

/**
 * One task field that differs from the newer catalog version. A conflict
 * means the founder also changed the field (or the base is unknown).
 */
final readonly class CatalogFieldDiff
{
    public function __construct(
        public string $field,
        public string $label,
        public mixed $founder,
        public mixed $catalog,
        public bool $isConflict,
    ) {}
}

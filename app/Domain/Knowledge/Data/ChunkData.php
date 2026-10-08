<?php

namespace App\Domain\Knowledge\Data;

final readonly class ChunkData
{
    public function __construct(
        public int $index,
        public ?string $headingPath,
        public string $content,
        public int $tokenCount,
    ) {}
}

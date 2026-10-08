<?php

namespace App\Domain\Knowledge\Data;

use App\Domain\Knowledge\Enums\DocType;

final readonly class SearchResultData
{
    public function __construct(
        public string $chunkId,
        public string $documentId,
        public string $documentTitle,
        public DocType $docType,
        public ?string $headingPath,
        public string $content,
        public float $score,
    ) {}
}

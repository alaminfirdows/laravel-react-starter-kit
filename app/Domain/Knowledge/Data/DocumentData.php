<?php

namespace App\Domain\Knowledge\Data;

use App\Domain\Knowledge\Enums\DocSource;
use App\Domain\Knowledge\Enums\DocStatus;
use App\Domain\Knowledge\Enums\DocType;

final readonly class DocumentData
{
    /**
     * @param  list<string>|null  $tags
     */
    public function __construct(
        public DocType $docType,
        public string $title,
        public string $bodyMd,
        public DocStatus $status = DocStatus::Draft,
        public DocSource $source = DocSource::User,
        public ?string $taskId = null,
        public ?array $tags = null,
        public ?string $changeNote = null,
    ) {}
}

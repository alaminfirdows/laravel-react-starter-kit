<?php

namespace App\Domain\Catalog\Data;

use App\Domain\Task\Enums\TaskPriority;

/**
 * Authored fields of a catalog task edited in the admin UI.
 */
final readonly class CatalogTaskData
{
    /**
     * @param  list<array<string, mixed>>|null  $completionCriteria
     * @param  list<array<string, mixed>>|null  $expectedOutputs
     */
    public function __construct(
        public string $key,
        public int $categoryId,
        public string $title,
        public ?int $parentId = null,
        public ?string $summary = null,
        public ?string $bodyMd = null,
        public TaskPriority $priority = TaskPriority::P2,
        public ?int $estMinutes = null,
        public ?int $difficulty = null,
        public bool $isOptional = false,
        public ?array $completionCriteria = null,
        public ?array $expectedOutputs = null,
    ) {}

    /**
     * Fields a project task copies and may upgrade (see DiffCatalogVersion).
     *
     * @return array<string, mixed>
     */
    public function authored(): array
    {
        return [
            'title' => $this->title,
            'summary' => $this->summary,
            'body_md' => $this->bodyMd,
            'priority_default' => $this->priority,
            'est_minutes' => $this->estMinutes,
            'difficulty' => $this->difficulty,
            'is_optional' => $this->isOptional,
            'completion_criteria' => $this->completionCriteria,
            'expected_outputs' => $this->expectedOutputs,
        ];
    }
}

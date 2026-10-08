<?php

namespace App\Domain\Catalog\Data;

use App\Domain\Task\Enums\ActionType;
use App\Domain\Task\Enums\Executor;

final readonly class CatalogActionData
{
    /**
     * @param  array<string, mixed>|null  $config
     */
    public function __construct(
        public string $key,
        public string $title,
        public ActionType $type,
        public Executor $executor,
        public ?string $instructionsMd = null,
        public ?int $promptTemplateId = null,
        public ?array $config = null,
        public bool $isRequired = true,
        public bool $requiresApproval = false,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(): array
    {
        return [
            'key' => $this->key,
            'title' => $this->title,
            'type' => $this->type,
            'executor' => $this->executor,
            'instructions_md' => $this->instructionsMd,
            'prompt_template_id' => $this->promptTemplateId,
            'config' => $this->config,
            'is_required' => $this->isRequired,
            'requires_approval' => $this->requiresApproval,
        ];
    }
}

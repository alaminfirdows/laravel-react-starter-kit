<?php

namespace App\Domain\Catalog\Data;

final readonly class PromptTemplateData
{
    /**
     * @param  list<string>|null  $skillKeys
     * @param  list<string>|null  $variables
     */
    public function __construct(
        public string $key,
        public string $title,
        public string $fullMd,
        public ?string $launcherMd = null,
        public string $target = 'chat',
        public ?array $skillKeys = null,
        public ?array $variables = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(): array
    {
        return [
            'key' => $this->key,
            'title' => $this->title,
            'launcher_md' => $this->launcherMd,
            'full_md' => $this->fullMd,
            'variables' => $this->variables,
            'target' => $this->target,
            'skill_keys' => $this->skillKeys,
        ];
    }
}

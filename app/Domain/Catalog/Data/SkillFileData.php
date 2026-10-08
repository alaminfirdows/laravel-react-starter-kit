<?php

namespace App\Domain\Catalog\Data;

/**
 * A validated `resources/skills/<name>/SKILL.md`.
 */
final readonly class SkillFileData
{
    public function __construct(
        public string $name,
        public string $description,
        public string $title,
        public string $version,
        public bool $inPlugin,
        public bool $inAppAgents,
        public string $directory,
        public string $contentHash,
    ) {}
}

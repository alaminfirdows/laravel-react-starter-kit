<?php

namespace App\Ai\Support;

use App\Domain\Catalog\Data\SkillFileData;
use App\Domain\Catalog\Support\SkillFile;
use Illuminate\Support\Collection;
use Laravel\Ai\Skills\Skill;

/**
 * The same `resources/skills/<name>/SKILL.md` files the plugin ships, for in-app agents.
 */
class SkillRepository
{
    /** @var Collection<string, SkillFileData>|null */
    private ?Collection $files = null;

    public function __construct(private ?string $directory = null)
    {
        $this->directory ??= resource_path('skills');
    }

    /**
     * Skills marked `metadata.in_app_agents: true`, optionally only the named ones.
     *
     * @param  list<string>  $names
     * @return list<Skill>
     */
    public function forAgents(array $names = []): array
    {
        return $this->files()
            ->filter(fn (SkillFileData $file): bool => $file->inAppAgents)
            ->when($names !== [], fn (Collection $files) => $files->only($names))
            ->map(fn (SkillFileData $file): ?Skill => Skill::fromDirectory($file->directory))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return Collection<string, SkillFileData>
     */
    private function files(): Collection
    {
        return $this->files ??= collect(SkillFile::find((string) $this->directory))
            ->map(SkillFile::read(...))
            ->keyBy('name');
    }
}

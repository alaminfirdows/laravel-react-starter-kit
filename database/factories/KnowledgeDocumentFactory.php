<?php

namespace Database\Factories;

use App\Domain\Knowledge\Enums\DocSource;
use App\Domain\Knowledge\Enums\DocStatus;
use App\Domain\Knowledge\Enums\DocType;
use App\Domain\Knowledge\Models\KnowledgeDocument;
use App\Domain\Project\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<KnowledgeDocument>
 */
class KnowledgeDocumentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $body = '# '.fake()->sentence(3)."\n\n".fake()->paragraph();

        return [
            'project_id' => Project::factory(),
            'workspace_id' => fn (array $attributes): string => Project::withoutWorkspaceScope()->whereKey($attributes['project_id'])->firstOrFail()->workspace_id,
            'doc_type' => DocType::Research,
            'title' => fake()->sentence(3),
            'body_md' => $body,
            'status' => DocStatus::Draft,
            'source' => DocSource::User,
            'version' => 1,
            'checksum' => fn (array $attributes): string => KnowledgeDocument::checksumFor($attributes['body_md']),
        ];
    }

    public function forProject(Project $project): static
    {
        return $this->state(['project_id' => $project->id, 'workspace_id' => $project->workspace_id]);
    }

    public function approved(): static
    {
        return $this->state(['status' => DocStatus::Approved]);
    }
}

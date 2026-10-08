<?php

namespace Database\Factories;

use App\Domain\Knowledge\Enums\DocSource;
use App\Domain\Knowledge\Models\Decision;
use App\Domain\Project\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Decision>
 */
class DecisionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'workspace_id' => fn (array $attributes): string => Project::withoutWorkspaceScope()->whereKey($attributes['project_id'])->firstOrFail()->workspace_id,
            'title' => fake()->sentence(4),
            'decision_md' => fake()->paragraph(),
            'decided_on' => now()->toDateString(),
            'source' => DocSource::User,
        ];
    }

    public function forProject(Project $project): static
    {
        return $this->state(['project_id' => $project->id, 'workspace_id' => $project->workspace_id]);
    }
}

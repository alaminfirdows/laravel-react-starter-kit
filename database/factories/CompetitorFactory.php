<?php

namespace Database\Factories;

use App\Domain\Knowledge\Models\Competitor;
use App\Domain\Project\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Competitor>
 */
class CompetitorFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'workspace_id' => fn (array $attributes): string => Project::withoutWorkspaceScope()->whereKey($attributes['project_id'])->firstOrFail()->workspace_id,
            'name' => fake()->company(),
            'url' => fake()->url(),
            'pricing' => 'From $'.fake()->numberBetween(5, 99).'/mo',
            'positioning' => fake()->sentence(),
        ];
    }

    public function forProject(Project $project): static
    {
        return $this->state(['project_id' => $project->id, 'workspace_id' => $project->workspace_id]);
    }
}

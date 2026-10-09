<?php

namespace Database\Factories;

use App\Domain\Knowledge\Models\Interview;
use App\Domain\Project\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Interview>
 */
class InterviewFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'workspace_id' => fn (array $attributes): string => Project::withoutWorkspaceScope()->whereKey($attributes['project_id'])->firstOrFail()->workspace_id,
            'person' => fake()->name(),
            'company' => fake()->company(),
            'role' => fake()->jobTitle(),
            'interviewed_on' => now()->toDateString(),
            'problem' => fake()->sentence(),
            'pain' => fake()->sentence(),
        ];
    }

    public function forProject(Project $project): static
    {
        return $this->state(['project_id' => $project->id, 'workspace_id' => $project->workspace_id]);
    }
}

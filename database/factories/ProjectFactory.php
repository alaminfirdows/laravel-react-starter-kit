<?php

namespace Database\Factories;

use App\Domain\Project\Enums\BusinessModel;
use App\Domain\Project\Enums\ProjectPhase;
use App\Domain\Project\Enums\ProjectStatus;
use App\Domain\Project\Enums\Stage;
use App\Domain\Project\Models\Project;
use App\Domain\Workspace\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'workspace_id' => Workspace::factory(),
            'owner_id' => fn (array $attributes): string => Workspace::query()->whereKey($attributes['workspace_id'])->firstOrFail()->owner_id,
            'name' => $name,
            'slug' => Str::slug($name),
            'phase' => ProjectPhase::Planning,
            'status' => ProjectStatus::Active,
            'one_liner' => fake()->sentence(8),
            'business_model' => BusinessModel::B2BSaas,
            'stage' => Stage::Idea,
            'primary_market' => 'US',
            'activated_at' => now(),
        ];
    }

    public function draft(): static
    {
        return $this->state([
            'status' => ProjectStatus::Draft,
            'activated_at' => null,
            'one_liner' => null,
            'stage' => null,
            'business_model' => null,
            'primary_market' => null,
        ]);
    }

    public function forWorkspace(Workspace $workspace): static
    {
        return $this->state(['workspace_id' => $workspace->id, 'owner_id' => $workspace->owner_id]);
    }
}

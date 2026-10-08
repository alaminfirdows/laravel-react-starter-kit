<?php

namespace Database\Factories;

use App\Domain\Project\Models\Project;
use App\Domain\Task\Enums\TaskPriority;
use App\Domain\Task\Enums\TaskStatus;
use App\Domain\Task\Enums\Verification;
use App\Domain\Task\Models\Task;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Task>
 */
class TaskFactory extends Factory
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
            'summary' => fake()->sentence(),
            'body_md' => fake()->paragraph(),
            'status' => TaskStatus::Todo,
            'priority' => TaskPriority::P2,
            'verification' => Verification::None,
            'depth' => 0,
            'sort_order' => 0,
            'category_key' => 'general',
        ];
    }

    public function forProject(Project $project): static
    {
        return $this->state(['project_id' => $project->id, 'workspace_id' => $project->workspace_id]);
    }

    public function childOf(Task $parent): static
    {
        return $this->state([
            'project_id' => $parent->project_id,
            'workspace_id' => $parent->workspace_id,
            'parent_id' => $parent->id,
            'depth' => $parent->depth + 1,
            'category_key' => $parent->category_key,
        ]);
    }

    public function done(): static
    {
        return $this->state([
            'status' => TaskStatus::Done,
            'progress_pct' => 100,
            'completed_at' => now(),
            'verification' => Verification::SelfReported,
        ]);
    }

    public function locked(): static
    {
        return $this->state(['status' => TaskStatus::Locked]);
    }
}

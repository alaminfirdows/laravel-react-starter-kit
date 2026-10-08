<?php

namespace Database\Factories;

use App\Domain\Task\Enums\ActionStatus;
use App\Domain\Task\Enums\ActionType;
use App\Domain\Task\Enums\Executor;
use App\Domain\Task\Models\Task;
use App\Domain\Task\Models\TaskAction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TaskAction>
 */
class TaskActionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'task_id' => Task::factory(),
            'project_id' => fn (array $attributes): string => Task::withoutWorkspaceScope()->whereKey($attributes['task_id'])->firstOrFail()->project_id,
            'type' => ActionType::Manual,
            'executor' => Executor::User,
            'title' => fake()->sentence(3),
            'instructions_md' => fake()->sentence(),
            'is_required' => true,
            'requires_approval' => false,
            'status' => ActionStatus::Pending,
            'sort_order' => 0,
        ];
    }

    public function forTask(Task $task): static
    {
        return $this->state(['task_id' => $task->id, 'project_id' => $task->project_id]);
    }
}

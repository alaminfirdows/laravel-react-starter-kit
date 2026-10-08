<?php

namespace Database\Factories;

use App\Domain\Activity\Enums\ActorType;
use App\Domain\Comment\Models\Comment;
use App\Domain\Task\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Comment>
 */
class CommentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'author_type' => ActorType::User,
            'author_id' => User::factory(),
            'body_md' => fake()->sentence(),
        ];
    }

    public function forTask(Task $task): static
    {
        return $this->state([
            'workspace_id' => $task->workspace_id,
            'project_id' => $task->project_id,
            'commentable_type' => $task->getMorphClass(),
            'commentable_id' => $task->id,
        ]);
    }
}

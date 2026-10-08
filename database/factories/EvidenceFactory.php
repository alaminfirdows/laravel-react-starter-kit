<?php

namespace Database\Factories;

use App\Domain\Activity\Enums\ActorType;
use App\Domain\Task\Enums\EvidenceKind;
use App\Domain\Task\Models\Evidence;
use App\Domain\Task\Models\TaskAction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Evidence>
 */
class EvidenceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'task_action_id' => TaskAction::factory(),
            'task_id' => fn (array $attributes): string => TaskAction::query()->whereKey($attributes['task_action_id'])->firstOrFail()->task_id,
            'project_id' => fn (array $attributes): string => TaskAction::query()->whereKey($attributes['task_action_id'])->firstOrFail()->project_id,
            'kind' => EvidenceKind::Url,
            'label' => fake()->sentence(2),
            'value' => fake()->url(),
            'created_by_type' => ActorType::User,
        ];
    }

    public function forAction(TaskAction $action, ?string $criterionKey = null): static
    {
        return $this->state([
            'task_action_id' => $action->id,
            'task_id' => $action->task_id,
            'project_id' => $action->project_id,
            'criterion_key' => $criterionKey,
        ]);
    }
}

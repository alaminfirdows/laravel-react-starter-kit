<?php

namespace Database\Factories;

use App\Domain\Activity\Enums\ActorType;
use App\Domain\Task\Enums\RunChannel;
use App\Domain\Task\Enums\RunStatus;
use App\Domain\Task\Models\ActionRun;
use App\Domain\Task\Models\TaskAction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ActionRun>
 */
class ActionRunFactory extends Factory
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
            'channel' => RunChannel::Mcp,
            'actor_type' => ActorType::Agent,
            'client_name' => 'Claude',
            'status' => RunStatus::Started,
            'started_at' => now(),
        ];
    }

    public function forAction(TaskAction $action): static
    {
        return $this->state(['task_action_id' => $action->id, 'task_id' => $action->task_id, 'project_id' => $action->project_id]);
    }
}

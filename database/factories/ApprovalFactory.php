<?php

namespace Database\Factories;

use App\Domain\Activity\Enums\ActorType;
use App\Domain\Task\Enums\ApprovalStatus;
use App\Domain\Task\Models\Approval;
use App\Domain\Task\Models\TaskAction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Approval>
 */
class ApprovalFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'subject_type' => (new TaskAction)->getMorphClass(),
            'subject_id' => TaskAction::factory(),
            'project_id' => fn (array $attributes): string => TaskAction::query()->whereKey($attributes['subject_id'])->firstOrFail()->project_id,
            'requested_by_type' => ActorType::Agent,
            'summary_md' => fake()->sentence(),
            'status' => ApprovalStatus::Pending,
        ];
    }

    public function forAction(TaskAction $action): static
    {
        return $this->state(['subject_type' => $action->getMorphClass(), 'subject_id' => $action->id, 'project_id' => $action->project_id]);
    }
}

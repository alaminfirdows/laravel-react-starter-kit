<?php

namespace Database\Factories;

use App\Domain\Workspace\Enums\WorkspaceRole;
use App\Domain\Workspace\Models\Workspace;
use App\Domain\Workspace\Models\WorkspaceInvitation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkspaceInvitation>
 */
class WorkspaceInvitationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'email' => fake()->unique()->safeEmail(),
            'role' => WorkspaceRole::Member,
            'expires_at' => now()->addDays(WorkspaceInvitation::EXPIRES_IN_DAYS),
        ];
    }

    public function expired(): static
    {
        return $this->state(['expires_at' => now()->subMinute()]);
    }

    public function accepted(): static
    {
        return $this->state(['accepted_at' => now()]);
    }
}

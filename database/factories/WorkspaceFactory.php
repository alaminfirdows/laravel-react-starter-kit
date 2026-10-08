<?php

namespace Database\Factories;

use App\Domain\Workspace\Enums\WorkspaceRole;
use App\Domain\Workspace\Enums\WorkspaceStatus;
use App\Domain\Workspace\Enums\WorkspaceType;
use App\Domain\Workspace\Models\Workspace;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Workspace>
 */
class WorkspaceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company(),
            'type' => WorkspaceType::Team,
            'status' => WorkspaceStatus::Active,
            'owner_id' => User::factory(),
        ];
    }

    /**
     * Owner gets an Owner membership, like CreateWorkspace does.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (Workspace $workspace): void {
            $workspace->memberships()->firstOrCreate(
                ['user_id' => $workspace->owner_id],
                ['role' => WorkspaceRole::Owner, 'joined_at' => now()],
            );
        });
    }

    public function personal(): static
    {
        return $this->state(['type' => WorkspaceType::Personal]);
    }

    public function suspended(): static
    {
        return $this->state(['status' => WorkspaceStatus::Suspended]);
    }

    public function inactive(): static
    {
        return $this->state(['status' => WorkspaceStatus::Inactive]);
    }

    public function ownedBy(User $user): static
    {
        return $this->state(['owner_id' => $user->id]);
    }

    /**
     * Add a member with the given role.
     */
    public function withMember(User $user, WorkspaceRole $role = WorkspaceRole::Member): static
    {
        return $this->afterCreating(function (Workspace $workspace) use ($user, $role): void {
            $workspace->memberships()->create([
                'user_id' => $user->id,
                'role' => $role,
                'joined_at' => now(),
            ]);
        });
    }
}

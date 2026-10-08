<?php

namespace Database\Factories;

use App\Domain\Workspace\Enums\WorkspaceRole;
use App\Domain\Workspace\Models\Workspace;
use App\Domain\Workspace\Models\WorkspaceMember;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkspaceMember>
 */
class WorkspaceMemberFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'user_id' => User::factory(),
            'role' => WorkspaceRole::Member,
            'joined_at' => now(),
        ];
    }

    public function role(WorkspaceRole $role): static
    {
        return $this->state(['role' => $role]);
    }
}

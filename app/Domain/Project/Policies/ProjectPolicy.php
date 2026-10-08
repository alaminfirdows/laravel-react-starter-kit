<?php

namespace App\Domain\Project\Policies;

use App\Domain\Project\Models\Project;
use App\Domain\Workspace\Enums\WorkspaceRole;
use App\Models\User;

class ProjectPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->roleIn($user) !== null;
    }

    public function view(User $user, Project $project): bool
    {
        return $user->workspaceRole($project->workspace) !== null;
    }

    public function create(User $user): bool
    {
        return $this->roleIn($user)?->isAtLeast(WorkspaceRole::Member) ?? false;
    }

    public function update(User $user, Project $project): bool
    {
        return $user->workspaceRole($project->workspace)?->isAtLeast(WorkspaceRole::Member) ?? false;
    }

    public function delete(User $user, Project $project): bool
    {
        $role = $user->workspaceRole($project->workspace);

        return $role !== null && ($role->isAtLeast(WorkspaceRole::Admin) || $project->owner_id === $user->id);
    }

    private function roleIn(User $user): ?WorkspaceRole
    {
        $workspace = currentWorkspace();

        return $workspace ? $user->workspaceRole($workspace) : null;
    }
}

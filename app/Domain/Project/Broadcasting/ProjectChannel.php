<?php

namespace App\Domain\Project\Broadcasting;

use App\Domain\Project\Models\Project;
use App\Models\User;

/**
 * Private channel `projects.{projectId}`: open to current workspace members only.
 * Removing a member makes the next auth request fail, so no further events reach them.
 */
class ProjectChannel
{
    public static function name(string $projectId): string
    {
        return "projects.{$projectId}";
    }

    public function join(User $user, string $projectId): bool
    {
        $project = Project::withoutWorkspaceScope()->with('workspace')->find($projectId);

        return $project !== null && $user->workspaceRole($project->workspace) !== null;
    }
}

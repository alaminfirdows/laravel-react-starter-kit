<?php

namespace App\Domain\Workspace\Policies;

use App\Domain\Workspace\Enums\WorkspacePermission;
use App\Domain\Workspace\Enums\WorkspaceRole;
use App\Domain\Workspace\Models\Workspace;
use App\Models\User;

class WorkspacePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Workspace $workspace): bool
    {
        return $user->belongsToWorkspace($workspace);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Workspace $workspace): bool
    {
        return $user->hasWorkspacePermission($workspace, WorkspacePermission::UpdateWorkspace);
    }

    public function delete(User $user, Workspace $workspace): bool
    {
        return ! $workspace->isPersonal()
            && $user->hasWorkspacePermission($workspace, WorkspacePermission::DeleteWorkspace);
    }

    public function transferOwnership(User $user, Workspace $workspace): bool
    {
        return ! $workspace->isPersonal()
            && $user->hasWorkspacePermission($workspace, WorkspacePermission::TransferOwnership);
    }

    /**
     * Owner must transfer ownership before leaving.
     */
    public function leave(User $user, Workspace $workspace): bool
    {
        if ($workspace->isPersonal()) {
            return false;
        }

        $role = $user->workspaceRole($workspace);

        return $role !== null && $role !== WorkspaceRole::Owner;
    }

    public function inviteMember(User $user, Workspace $workspace): bool
    {
        return ! $workspace->isPersonal()
            && $user->hasWorkspacePermission($workspace, WorkspacePermission::CreateInvitation);
    }

    public function cancelInvitation(User $user, Workspace $workspace): bool
    {
        return $user->hasWorkspacePermission($workspace, WorkspacePermission::CancelInvitation);
    }

    public function updateAnyMember(User $user, Workspace $workspace): bool
    {
        return ! $workspace->isPersonal()
            && $user->hasWorkspacePermission($workspace, WorkspacePermission::UpdateMember);
    }

    public function removeAnyMember(User $user, Workspace $workspace): bool
    {
        return ! $workspace->isPersonal()
            && $user->hasWorkspacePermission($workspace, WorkspacePermission::RemoveMember);
    }

    /**
     * Actor can only change members ranked below them.
     */
    public function updateMember(User $user, Workspace $workspace, User $member): bool
    {
        return $this->updateAnyMember($user, $workspace)
            && $this->outranks($user, $member, $workspace);
    }

    public function removeMember(User $user, Workspace $workspace, User $member): bool
    {
        return $this->removeAnyMember($user, $workspace)
            && $this->outranks($user, $member, $workspace);
    }

    protected function outranks(User $user, User $member, Workspace $workspace): bool
    {
        if ($user->is($member)) {
            return false;
        }

        $actorRole = $user->workspaceRole($workspace);
        $memberRole = $member->workspaceRole($workspace);

        return $actorRole !== null
            && $memberRole !== null
            && $actorRole->outranks($memberRole);
    }
}

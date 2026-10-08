<?php

namespace App\Domain\Workspace\Actions;

use App\Domain\Workspace\Enums\WorkspaceRole;
use App\Domain\Workspace\Models\Workspace;
use App\Models\User;
use InvalidArgumentException;

class UpdateMemberRole
{
    /**
     * Ownership moves only through TransferOwnership.
     */
    public function handle(Workspace $workspace, User $member, WorkspaceRole $role): void
    {
        if ($role === WorkspaceRole::Owner) {
            throw new InvalidArgumentException('Use TransferOwnership to change the owner.');
        }

        $workspace->memberships()
            ->where('user_id', $member->id)
            ->where('role', '!=', WorkspaceRole::Owner->value)
            ->firstOrFail()
            ->update(['role' => $role]);
    }
}

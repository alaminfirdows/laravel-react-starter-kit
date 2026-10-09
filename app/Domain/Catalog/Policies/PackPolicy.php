<?php

namespace App\Domain\Catalog\Policies;

use App\Domain\Catalog\Models\Pack;
use App\Domain\Workspace\Enums\WorkspaceRole;
use App\Models\User;

/**
 * Community packs: members of the owner workspace manage them. Official
 * packs are edited only through the admin area (`can:admin`).
 */
class PackPolicy
{
    public function create(User $user): bool
    {
        $workspace = currentWorkspace();

        return $workspace !== null && ($user->workspaceRole($workspace)?->isAtLeast(WorkspaceRole::Member) ?? false);
    }

    public function update(User $user, Pack $pack): bool
    {
        return $pack->ownerWorkspace !== null
            && ($user->workspaceRole($pack->ownerWorkspace)?->isAtLeast(WorkspaceRole::Member) ?? false);
    }
}

<?php

namespace App\Domain\Workspace\Data;

/**
 * Boolean flags the frontend uses for conditional UI.
 */
readonly class WorkspacePermissions
{
    public function __construct(
        public bool $canUpdateWorkspace,
        public bool $canDeleteWorkspace,
        public bool $canTransferOwnership,
        public bool $canLeaveWorkspace,
        public bool $canUpdateMember,
        public bool $canRemoveMember,
        public bool $canCreateInvitation,
        public bool $canCancelInvitation,
    ) {}
}

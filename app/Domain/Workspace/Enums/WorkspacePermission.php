<?php

namespace App\Domain\Workspace\Enums;

enum WorkspacePermission: string
{
    case UpdateWorkspace = 'workspace:update';
    case DeleteWorkspace = 'workspace:delete';
    case TransferOwnership = 'workspace:transfer';
    case UpdateMember = 'member:update';
    case RemoveMember = 'member:remove';
    case CreateInvitation = 'invitation:create';
    case CancelInvitation = 'invitation:cancel';
}

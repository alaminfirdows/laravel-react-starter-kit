export type WorkspaceRole = 'owner' | 'admin' | 'member' | 'viewer';

export type WorkspaceStatus = 'active' | 'inactive' | 'suspended';

export type RoleOption = {
    value: WorkspaceRole;
    label: string;
};

export type UserWorkspace = {
    id: string;
    name: string;
    slug: string;
    isPersonal: boolean;
    status: WorkspaceStatus;
    logoUrl: string | null;
    role: WorkspaceRole | null;
    roleLabel: string | null;
    isCurrent: boolean;
};

export type WorkspacePermissions = {
    canUpdateWorkspace: boolean;
    canDeleteWorkspace: boolean;
    canTransferOwnership: boolean;
    canLeaveWorkspace: boolean;
    canUpdateMember: boolean;
    canRemoveMember: boolean;
    canCreateInvitation: boolean;
    canCancelInvitation: boolean;
};

export type WorkspaceDetails = {
    id: string;
    name: string;
    slug: string;
    logoUrl: string | null;
    isPersonal: boolean;
    status: WorkspaceStatus;
    createdAt: string | null;
};

export type WorkspaceMember = {
    id: string;
    name: string;
    email: string;
    role: WorkspaceRole;
    roleLabel: string;
    joinedAt: string | null;
    isCurrentUser: boolean;
    canUpdate: boolean;
    canRemove: boolean;
};

export type WorkspaceInvitation = {
    code: string;
    email: string;
    role: WorkspaceRole;
    roleLabel: string;
    inviterName: string | null;
    isExpired: boolean;
    expiresAt: string | null;
    createdAt: string | null;
};

export type PendingInvitation = {
    code: string;
    role: WorkspaceRole;
    roleLabel: string;
    workspaceName: string;
    workspaceLogoUrl: string | null;
    inviterName: string | null;
    expiresAt: string | null;
};

export type InvitationStatus =
    | 'pending'
    | 'accepted'
    | 'expired'
    | 'unavailable';

export type InvitationDetails = PendingInvitation & {
    email: string;
    status: InvitationStatus;
};

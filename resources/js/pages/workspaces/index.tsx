import { Head, Link, router } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useState } from 'react';
import InvitationController from '@/actions/App/Domain/Workspace/Http/Controllers/InvitationController';
import { CreateWorkspaceDialog } from '@/components/create-workspace-dialog';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { WorkspaceAvatar } from '@/components/workspace-avatar';
import { dashboard } from '@/routes';
import type { PendingInvitation, UserWorkspace } from '@/types';

export default function WorkspacesIndex({
    workspaces,
    pendingInvitations,
}: {
    workspaces: UserWorkspace[];
    pendingInvitations: PendingInvitation[];
}) {
    const [creating, setCreating] = useState(false);

    return (
        <>
            <Head title="Workspaces" />

            <div className="mx-auto w-full max-w-3xl space-y-10 px-4 py-6">
                {pendingInvitations.length > 0 && (
                    <section className="space-y-4">
                        <Heading
                            variant="small"
                            title="Invitations"
                            description="Workspaces that invited you"
                        />
                        <ul className="divide-y rounded-lg border">
                            {pendingInvitations.map((invitation) => (
                                <li
                                    key={invitation.code}
                                    className="flex flex-wrap items-center gap-3 p-4"
                                >
                                    <WorkspaceAvatar
                                        name={invitation.workspaceName}
                                        logoUrl={invitation.workspaceLogoUrl}
                                    />
                                    <div className="min-w-0 flex-1">
                                        <p className="truncate text-sm font-medium">
                                            {invitation.workspaceName}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            {invitation.inviterName
                                                ? `${invitation.inviterName} invited you as ${invitation.roleLabel}`
                                                : `Invited as ${invitation.roleLabel}`}
                                        </p>
                                    </div>
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        onClick={() =>
                                            router.visit(
                                                InvitationController.decline(
                                                    invitation.code,
                                                ),
                                            )
                                        }
                                    >
                                        Decline
                                    </Button>
                                    <Button
                                        size="sm"
                                        onClick={() =>
                                            router.visit(
                                                InvitationController.accept(
                                                    invitation.code,
                                                ),
                                            )
                                        }
                                    >
                                        Accept
                                    </Button>
                                </li>
                            ))}
                        </ul>
                    </section>
                )}

                <section className="space-y-4">
                    <div className="flex items-start justify-between gap-4">
                        <Heading
                            variant="small"
                            title="Your workspaces"
                            description="Workspaces you are a member of"
                        />
                        <Button size="sm" onClick={() => setCreating(true)}>
                            <Plus />
                            New workspace
                        </Button>
                    </div>

                    <ul className="divide-y rounded-lg border">
                        {workspaces.map((workspace) => (
                            <li key={workspace.id}>
                                <Link
                                    href={dashboard(workspace.slug)}
                                    className="flex items-center gap-3 p-4 hover:bg-muted/50"
                                >
                                    <WorkspaceAvatar
                                        name={workspace.name}
                                        logoUrl={workspace.logoUrl}
                                    />
                                    <span className="min-w-0 flex-1 truncate text-sm font-medium">
                                        {workspace.name}
                                    </span>
                                    {workspace.isPersonal && (
                                        <Badge variant="outline">
                                            Personal
                                        </Badge>
                                    )}
                                    {workspace.status !== 'active' && (
                                        <Badge variant="destructive">
                                            {workspace.status}
                                        </Badge>
                                    )}
                                    <Badge variant="secondary">
                                        {workspace.roleLabel}
                                    </Badge>
                                </Link>
                            </li>
                        ))}
                    </ul>
                </section>
            </div>

            <CreateWorkspaceDialog open={creating} onOpenChange={setCreating} />
        </>
    );
}

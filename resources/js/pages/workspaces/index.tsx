import { Head, Link, router } from '@inertiajs/react';
import { Plus, Users } from '@/components/animated-icons';
import { useState } from 'react';
import InvitationController from '@/actions/App/Domain/Workspace/Http/Controllers/InvitationController';
import { CreateWorkspaceDialog } from '@/components/create-workspace-dialog';
import { EmptyState } from '@/components/empty-state';
import { Page } from '@/components/page';
import { PageHeader } from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
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

            <Page>
                <PageHeader
                    title="Workspaces"
                    description="Switch between workspaces or start a new one."
                    actions={
                        <Button onClick={() => setCreating(true)}>
                            <Plus />
                            New workspace
                        </Button>
                    }
                />
                {pendingInvitations.length > 0 && (
                    <section className="flex flex-col gap-3">
                        <h2 className="text-sm font-semibold">Invitations</h2>
                        <Card className="gap-0 py-0">
                            <ul className="divide-y">
                                {pendingInvitations.map((invitation) => (
                                    <li
                                        key={invitation.code}
                                        className="flex flex-wrap items-center gap-3 p-4"
                                    >
                                        <WorkspaceAvatar
                                            name={invitation.workspaceName}
                                            logoUrl={
                                                invitation.workspaceLogoUrl
                                            }
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
                        </Card>
                    </section>
                )}

                <section className="flex flex-col gap-3">
                    <h2 className="text-sm font-semibold">Your workspaces</h2>
                    {workspaces.length === 0 ? (
                        <EmptyState
                            icon={Users}
                            size="sm"
                            title="No workspaces yet."
                            description="Create a workspace to get started."
                        />
                    ) : (
                        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                            {workspaces.map((workspace) => (
                                <Link
                                    key={workspace.id}
                                    href={dashboard(workspace.slug)}
                                    className="rounded-xl outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                >
                                    <Card className="h-full transition-colors hover:border-primary/50 hover:bg-muted/30">
                                        <div className="flex items-center gap-3">
                                            <WorkspaceAvatar
                                                name={workspace.name}
                                                logoUrl={workspace.logoUrl}
                                                className="size-10"
                                            />
                                            <span className="min-w-0 flex-1 truncate text-sm font-semibold">
                                                {workspace.name}
                                            </span>
                                        </div>
                                        <div className="mt-auto flex flex-wrap items-center gap-2">
                                            <Badge variant="secondary">
                                                {workspace.roleLabel}
                                            </Badge>
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
                                        </div>
                                    </Card>
                                </Link>
                            ))}
                        </div>
                    )}
                </section>
            </Page>

            <CreateWorkspaceDialog open={creating} onOpenChange={setCreating} />
        </>
    );
}

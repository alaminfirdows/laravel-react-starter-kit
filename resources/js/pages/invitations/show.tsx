import { Head, Link, router } from '@inertiajs/react';
import InvitationController from '@/actions/App/Domain/Workspace/Http/Controllers/InvitationController';
import { Button } from '@/components/ui/button';
import { WorkspaceAvatar } from '@/components/workspace-avatar';
import { dashboard, login, logout, register } from '@/routes';
import { index as workspacesIndex } from '@/routes/workspaces';
import type { InvitationDetails } from '@/types';

export default function ShowInvitation({
    invitation,
    isGuest,
    emailMatches,
    workspaceSlug,
}: {
    invitation: InvitationDetails;
    isGuest: boolean;
    emailMatches: boolean;
    workspaceSlug: string | null;
}) {
    return (
        <>
            <Head title={`Join ${invitation.workspaceName}`} />

            <div className="flex flex-col items-center gap-4 text-center">
                <WorkspaceAvatar
                    name={invitation.workspaceName}
                    logoUrl={invitation.workspaceLogoUrl}
                    className="size-14 text-lg"
                />
                <div className="flex flex-col gap-1">
                    <h1 className="text-xl font-semibold tracking-tight">
                        {invitation.workspaceName}
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        {invitation.inviterName
                            ? `${invitation.inviterName} invited ${invitation.email} to join as ${invitation.roleLabel}.`
                            : `${invitation.email} is invited to join as ${invitation.roleLabel}.`}
                    </p>
                </div>

                <Body
                    invitation={invitation}
                    isGuest={isGuest}
                    emailMatches={emailMatches}
                    workspaceSlug={workspaceSlug}
                />
            </div>
        </>
    );
}

function Body({
    invitation,
    isGuest,
    emailMatches,
    workspaceSlug,
}: {
    invitation: InvitationDetails;
    isGuest: boolean;
    emailMatches: boolean;
    workspaceSlug: string | null;
}) {
    if (invitation.status === 'unavailable') {
        return <Notice>This workspace no longer exists.</Notice>;
    }

    if (invitation.status === 'accepted') {
        return (
            <>
                <Notice>This invitation was already accepted.</Notice>
                {workspaceSlug && (
                    <Button variant="primary" asChild>
                        <Link href={dashboard(workspaceSlug)}>
                            Open workspace
                        </Link>
                    </Button>
                )}
            </>
        );
    }

    if (invitation.status === 'expired') {
        return (
            <Notice>
                This invitation has expired. Ask for a new invitation.
            </Notice>
        );
    }

    if (isGuest) {
        return (
            <div className="flex w-full flex-col gap-2">
                <Button variant="primary" asChild>
                    <Link href={login()}>Log in to accept</Link>
                </Button>
                <Button variant="secondary" asChild>
                    <Link href={register()}>Create an account</Link>
                </Button>
                <p className="text-xs text-muted-foreground">
                    Use {invitation.email}.
                </p>
            </div>
        );
    }

    if (!emailMatches) {
        return (
            <>
                <Notice>
                    This invitation is for {invitation.email}. Log in with that
                    account to accept it.
                </Notice>
                <div className="flex gap-2">
                    <Button variant="secondary" asChild>
                        <Link href={workspacesIndex()}>Back</Link>
                    </Button>
                    <Button variant="secondary" asChild>
                        <Link href={logout()} as="button">
                            Log out
                        </Link>
                    </Button>
                </div>
            </>
        );
    }

    return (
        <div className="flex w-full gap-2">
            <Button
                variant="secondary"
                className="flex-1"
                onClick={() =>
                    router.visit(InvitationController.decline(invitation.code))
                }
                data-test="decline-invitation-button"
            >
                Decline
            </Button>
            <Button
                variant="primary"
                className="flex-1"
                onClick={() =>
                    router.visit(InvitationController.accept(invitation.code))
                }
                data-test="accept-invitation-button"
            >
                Accept
            </Button>
        </div>
    );
}

function Notice({ children }: { children: React.ReactNode }) {
    return (
        <p className="w-full rounded-md border bg-muted/50 p-3 text-sm">
            {children}
        </p>
    );
}

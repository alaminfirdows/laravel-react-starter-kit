import { Link, usePage } from '@inertiajs/react';
import type { PropsWithChildren } from 'react';
import { Page } from '@/components/page';
import { PageHeader } from '@/components/page-header';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { cn, toUrl } from '@/lib/utils';
import { index as activityIndex } from '@/routes/workspace/activity';
import { index as invitationsIndex } from '@/routes/workspace/invitations';
import { index as membersIndex } from '@/routes/workspace/members';
import { edit as editGeneral } from '@/routes/workspace/settings';
import type { NavItem } from '@/types';

export default function WorkspaceSettingsLayout({
    children,
}: PropsWithChildren) {
    const { isCurrentOrParentUrl } = useCurrentUrl();
    const { currentWorkspace, workspacePermissions } = usePage().props;

    // Built in render: URLs need the {workspace} default from AppLayout.
    const navItems: NavItem[] = [
        { title: 'General', href: editGeneral() },
        ...(currentWorkspace?.isPersonal
            ? []
            : [{ title: 'Members', href: membersIndex() }]),
        ...(workspacePermissions?.canCreateInvitation
            ? [{ title: 'Invitations', href: invitationsIndex() }]
            : []),
        ...(workspacePermissions?.canUpdateWorkspace
            ? [{ title: 'Activity', href: activityIndex() }]
            : []),
    ];

    return (
        <Page size="narrow" className="max-w-4xl">
            <PageHeader
                title="Workspace settings"
                description={`Manage ${currentWorkspace?.name ?? 'workspace'} settings and members`}
            />

            <div className="flex flex-col gap-6 md:flex-row md:gap-10">
                <nav
                    className="-mx-4 flex gap-1 overflow-x-auto px-4 md:mx-0 md:w-44 md:shrink-0 md:flex-col md:overflow-visible md:px-0"
                    aria-label="Workspace settings"
                >
                    {navItems.map((item) => (
                        <Link
                            key={toUrl(item.href)}
                            href={item.href}
                            className={cn(
                                'shrink-0 rounded-md px-3 py-1.5 text-sm whitespace-nowrap transition-colors',
                                isCurrentOrParentUrl(item.href)
                                    ? 'bg-muted font-medium text-foreground'
                                    : 'text-muted-foreground hover:bg-muted/50 hover:text-foreground',
                            )}
                        >
                            {item.title}
                        </Link>
                    ))}
                </nav>

                <div className="flex max-w-2xl min-w-0 flex-1 flex-col gap-6">
                    {children}
                </div>
            </div>
        </Page>
    );
}

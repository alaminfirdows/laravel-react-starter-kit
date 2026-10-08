import { Link, usePage } from '@inertiajs/react';
import type { PropsWithChildren } from 'react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
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
        <div className="px-4 py-6">
            <Heading
                title="Workspace settings"
                description={`Manage ${currentWorkspace?.name ?? 'workspace'} settings and members`}
            />

            <div className="flex flex-col lg:flex-row lg:space-x-12">
                <aside className="w-full max-w-xl lg:w-48">
                    <nav
                        className="flex flex-col space-y-1 space-x-0"
                        aria-label="Workspace settings"
                    >
                        {navItems.map((item) => (
                            <Button
                                key={toUrl(item.href)}
                                size="sm"
                                variant="ghost"
                                asChild
                                className={cn('w-full justify-start', {
                                    'bg-muted': isCurrentOrParentUrl(item.href),
                                })}
                            >
                                <Link href={item.href}>{item.title}</Link>
                            </Button>
                        ))}
                    </nav>
                </aside>

                <Separator className="my-6 lg:hidden" />

                <div className="flex-1 md:max-w-3xl">
                    <section className="max-w-2xl space-y-12">
                        {children}
                    </section>
                </div>
            </div>
        </div>
    );
}

import { Link, usePage } from '@inertiajs/react';
import {
    Check,
    ChevronsUpDown,
    Layers,
    Mail,
    Plus,
    Settings,
} from 'lucide-react';
import { useState } from 'react';
import { CreateWorkspaceDialog } from '@/components/create-workspace-dialog';
import { WorkspaceAvatar } from '@/components/workspace-avatar';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    useSidebar,
} from '@/components/ui/sidebar';
import { useIsMobile } from '@/hooks/use-mobile';
import { dashboard } from '@/routes';
import { edit as editWorkspaceSettings } from '@/routes/workspace/settings';
import { index as workspacesIndex } from '@/routes/workspaces';

export function WorkspaceSwitcher() {
    const { currentWorkspace, workspaces, pendingInvitationsCount } =
        usePage().props;
    const { state } = useSidebar();
    const isMobile = useIsMobile();
    const [creating, setCreating] = useState(false);

    return (
        <>
            <SidebarMenu>
                <SidebarMenuItem>
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <SidebarMenuButton
                                size="lg"
                                className="data-[state=open]:bg-sidebar-accent"
                                data-test="workspace-switcher"
                            >
                                <WorkspaceAvatar
                                    name={currentWorkspace?.name ?? '?'}
                                    logoUrl={currentWorkspace?.logoUrl ?? null}
                                />
                                <div className="grid flex-1 text-left text-sm leading-tight">
                                    <span className="truncate font-semibold">
                                        {currentWorkspace?.name ??
                                            'Select workspace'}
                                    </span>
                                    {currentWorkspace?.roleLabel && (
                                        <span className="truncate text-xs text-muted-foreground">
                                            {currentWorkspace.isPersonal
                                                ? 'Personal'
                                                : currentWorkspace.roleLabel}
                                        </span>
                                    )}
                                </div>
                                <ChevronsUpDown className="ml-auto size-4" />
                            </SidebarMenuButton>
                        </DropdownMenuTrigger>

                        <DropdownMenuContent
                            className="w-(--radix-dropdown-menu-trigger-width) min-w-64 rounded-lg"
                            align="start"
                            side={
                                isMobile || state !== 'collapsed'
                                    ? 'bottom'
                                    : 'right'
                            }
                        >
                            <DropdownMenuLabel className="text-xs text-muted-foreground">
                                Workspaces
                            </DropdownMenuLabel>

                            {workspaces.map((workspace) => (
                                <DropdownMenuItem key={workspace.id} asChild>
                                    <Link
                                        href={dashboard(workspace.slug)}
                                        className="gap-2"
                                    >
                                        <WorkspaceAvatar
                                            name={workspace.name}
                                            logoUrl={workspace.logoUrl}
                                            className="size-6"
                                        />
                                        <span className="flex-1 truncate">
                                            {workspace.name}
                                        </span>
                                        {workspace.id ===
                                            currentWorkspace?.id && (
                                            <Check className="size-4" />
                                        )}
                                    </Link>
                                </DropdownMenuItem>
                            ))}

                            <DropdownMenuSeparator />

                            {currentWorkspace && (
                                <DropdownMenuItem asChild>
                                    <Link href={editWorkspaceSettings()}>
                                        <Settings />
                                        Workspace settings
                                    </Link>
                                </DropdownMenuItem>
                            )}
                            <DropdownMenuItem asChild>
                                <Link href={workspacesIndex()}>
                                    {pendingInvitationsCount > 0 ? (
                                        <Mail />
                                    ) : (
                                        <Layers />
                                    )}
                                    <span className="flex-1">
                                        All workspaces
                                    </span>
                                    {pendingInvitationsCount > 0 && (
                                        <span className="rounded-full bg-primary px-1.5 text-xs text-primary-foreground">
                                            {pendingInvitationsCount}
                                        </span>
                                    )}
                                </Link>
                            </DropdownMenuItem>
                            <DropdownMenuItem
                                onSelect={() => setCreating(true)}
                                data-test="open-create-workspace"
                            >
                                <Plus />
                                Create workspace
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </SidebarMenuItem>
            </SidebarMenu>

            <CreateWorkspaceDialog open={creating} onOpenChange={setCreating} />
        </>
    );
}

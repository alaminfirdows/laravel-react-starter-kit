import { CommandPaletteTrigger } from '@/components/command-palette';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import { WorkspaceSwitcher } from '@/components/workspace-switcher';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
} from '@/components/ui/sidebar';
import { useMainNav } from '@/hooks/use-main-nav';

export function AppSidebar() {
    const nav = useMainNav();

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader className="gap-2">
                <WorkspaceSwitcher />
                <CommandPaletteTrigger className="w-full group-data-[collapsible=icon]:hidden" />
            </SidebarHeader>

            <SidebarContent>
                <NavMain label="Workspace" items={nav.workspace} />
                <NavMain label="Manage" items={nav.manage} />
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}

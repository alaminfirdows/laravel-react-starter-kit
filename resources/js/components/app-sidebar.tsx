import {
    BookOpen,
    FolderGit2,
    FolderKanban,
    ListTodo,
    Package,
    LayoutGrid,
    Settings,
    ShieldCheck,
} from 'lucide-react';
import { usePage } from '@inertiajs/react';
import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import { WorkspaceSwitcher } from '@/components/workspace-switcher';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import { index as adminIndex } from '@/routes/admin';
import { index as packsIndex } from '@/routes/packs';
import { index as projectsIndex } from '@/routes/projects';
import { mine as myTasks } from '@/routes/tasks';
import { edit as editWorkspaceSettings } from '@/routes/workspace/settings';
import type { NavItem } from '@/types';

const footerNavItems: NavItem[] = [
    {
        title: 'Repository',
        href: 'https://github.com/laravel/react-starter-kit',
        icon: FolderGit2,
    },
    {
        title: 'Documentation',
        href: 'https://laravel.com/docs/starter-kits#react',
        icon: BookOpen,
    },
];

export function AppSidebar() {
    const { auth } = usePage().props;
    // Built in render: workspace URLs need the URL defaults set by AppLayout.
    const mainNavItems: NavItem[] = [
        {
            title: 'Dashboard',
            href: dashboard(),
            icon: LayoutGrid,
        },
        {
            title: 'Projects',
            href: projectsIndex(),
            icon: FolderKanban,
        },
        {
            title: 'My tasks',
            href: myTasks(),
            icon: ListTodo,
        },
        {
            title: 'Packs',
            href: packsIndex(),
            icon: Package,
        },
        {
            title: 'Workspace settings',
            href: editWorkspaceSettings(),
            icon: Settings,
        },
        ...(auth.user.is_admin
            ? [{ title: 'Admin', href: adminIndex(), icon: ShieldCheck }]
            : []),
    ];

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <WorkspaceSwitcher />
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} />
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}

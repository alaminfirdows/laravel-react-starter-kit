import { usePage } from '@inertiajs/react';
import {
    FolderKanban,
    LayoutGrid,
    ListTodo,
    Package,
    Settings,
    ShieldCheck,
} from '@/components/animated-icons';
import { dashboard } from '@/routes';
import { index as adminIndex } from '@/routes/admin';
import { index as packsIndex } from '@/routes/packs';
import { index as projectsIndex } from '@/routes/projects';
import { mine as myTasks } from '@/routes/tasks';
import { edit as editWorkspaceSettings } from '@/routes/workspace/settings';
import type { NavItem } from '@/types';

/**
 * Primary app destinations, shared by the sidebar and the command palette.
 * Built in render: workspace URLs need the URL defaults set by the layout.
 */
export function useMainNav(): { workspace: NavItem[]; manage: NavItem[] } {
    const { auth } = usePage().props;

    return {
        workspace: [
            { title: 'Dashboard', href: dashboard(), icon: LayoutGrid },
            { title: 'Projects', href: projectsIndex(), icon: FolderKanban },
            { title: 'My tasks', href: myTasks(), icon: ListTodo },
            { title: 'Packs', href: packsIndex(), icon: Package },
        ],
        manage: [
            {
                title: 'Workspace settings',
                href: editWorkspaceSettings(),
                icon: Settings,
            },
            ...(auth.user?.is_admin
                ? [{ title: 'Admin', href: adminIndex(), icon: ShieldCheck }]
                : []),
        ],
    };
}

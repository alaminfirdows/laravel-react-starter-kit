import type { InertiaLinkProps } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import {
    BookOpen,
    ClipboardList,
    History,
    LayoutDashboard,
    Scale,
    ShieldQuestion,
} from 'lucide-react';
import { show } from '@/routes/projects';
import { index as activityIndex } from '@/routes/projects/activity';
import { index as approvalsIndex } from '@/routes/projects/approvals';
import { index as decisionsIndex } from '@/routes/projects/decisions';
import { index as knowledgeIndex } from '@/routes/projects/knowledge';
import { index as researchIndex } from '@/routes/projects/research';

export type ProjectSection = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
    icon: LucideIcon;
    exact: boolean;
};

/**
 * Project sub-pages, shared by the project tabs and the command palette.
 */
export function projectSections(projectSlug: string): ProjectSection[] {
    const project = projectSlug;

    return [
        {
            title: 'Overview',
            href: show({ project }),
            icon: LayoutDashboard,
            exact: true,
        },
        {
            title: 'Knowledge',
            href: knowledgeIndex({ project }),
            icon: BookOpen,
            exact: false,
        },
        {
            title: 'Research',
            href: researchIndex({ project }),
            icon: ClipboardList,
            exact: false,
        },
        {
            title: 'Decisions',
            href: decisionsIndex({ project }),
            icon: Scale,
            exact: false,
        },
        {
            title: 'Approvals',
            href: approvalsIndex({ project }),
            icon: ShieldQuestion,
            exact: false,
        },
        {
            title: 'Activity',
            href: activityIndex({ project }),
            icon: History,
            exact: false,
        },
    ];
}

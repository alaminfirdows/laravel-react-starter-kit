import { Link, usePage } from '@inertiajs/react';
import {
    ArrowLeft,
    BookOpen,
    ClipboardList,
    History,
    LayoutDashboard,
    Scale,
    ShieldQuestion,
} from 'lucide-react';
import { CommandPaletteTrigger } from '@/components/command-palette';
import { navItemClassName } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import { TaskTree } from '@/components/project/task-tree';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarGroup,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuBadge,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { WorkspaceAvatar } from '@/components/workspace-avatar';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { index, show } from '@/routes/projects';
import { index as activityIndex } from '@/routes/projects/activity';
import { index as approvalsIndex } from '@/routes/projects/approvals';
import { index as decisionsIndex } from '@/routes/projects/decisions';
import { index as knowledgeIndex } from '@/routes/projects/knowledge';
import { index as researchIndex } from '@/routes/projects/research';
import { edit } from '@/routes/projects/setup';
import type { ProjectPageProps, Task } from '@/types';

export function ProjectSidebar() {
    const { project, tree, task, pendingApprovals } = usePage<
        ProjectPageProps & { task?: Task }
    >().props;
    const { isCurrentUrl, isCurrentOrParentUrl } = useCurrentUrl();
    const links = [
        {
            title: 'Overview',
            href: show({ project: project.slug }),
            icon: LayoutDashboard,
            exact: true,
        },
        {
            title: 'Knowledge',
            href: knowledgeIndex({ project: project.slug }),
            icon: BookOpen,
            exact: false,
        },
        {
            title: 'Research',
            href: researchIndex({ project: project.slug }),
            icon: ClipboardList,
            exact: false,
        },
        {
            title: 'Decisions',
            href: decisionsIndex({ project: project.slug }),
            icon: Scale,
            exact: false,
        },
        {
            title: 'Approvals',
            href: approvalsIndex({ project: project.slug }),
            icon: ShieldQuestion,
            exact: false,
            badge: pendingApprovals,
        },
        {
            title: 'Activity',
            href: activityIndex({ project: project.slug }),
            icon: History,
            exact: false,
        },
    ];

    return (
        <Sidebar collapsible="offcanvas" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton asChild size="sm">
                            <Link href={index()}>
                                <ArrowLeft /> All projects
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                    <SidebarMenuItem>
                        <SidebarMenuButton asChild size="lg">
                            <Link href={show({ project: project.slug })}>
                                <WorkspaceAvatar
                                    name={project.name}
                                    logoUrl={project.logoUrl}
                                />
                                <span className="truncate font-medium">
                                    {project.name}
                                </span>
                                <Badge variant="secondary" className="ml-auto">
                                    {project.phaseLabel}
                                </Badge>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
                {project.setupStep && (
                    <Button size="sm" asChild>
                        <Link
                            href={edit({
                                project: project.slug,
                                step: project.setupStep,
                            })}
                        >
                            Finish setup
                        </Link>
                    </Button>
                )}
                <CommandPaletteTrigger className="w-full" />
            </SidebarHeader>
            <SidebarContent>
                <SidebarGroup>
                    <SidebarMenu>
                        {links.map((link) => (
                            <SidebarMenuItem key={link.title}>
                                <SidebarMenuButton
                                    asChild
                                    className={navItemClassName}
                                    isActive={
                                        link.exact
                                            ? isCurrentUrl(link.href)
                                            : isCurrentOrParentUrl(link.href)
                                    }
                                >
                                    <Link href={link.href}>
                                        <link.icon /> {link.title}
                                    </Link>
                                </SidebarMenuButton>
                                {'badge' in link && !!link.badge && (
                                    <SidebarMenuBadge>
                                        {link.badge}
                                    </SidebarMenuBadge>
                                )}
                            </SidebarMenuItem>
                        ))}
                    </SidebarMenu>
                </SidebarGroup>
                <TaskTree
                    groups={tree.groups}
                    projectSlug={project.slug}
                    activeTaskId={task?.id}
                />
            </SidebarContent>
            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}

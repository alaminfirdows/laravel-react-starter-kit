import { Link, usePage } from '@inertiajs/react';
import { ArrowLeft, BookOpen, LayoutDashboard, Scale } from 'lucide-react';
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
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { WorkspaceAvatar } from '@/components/workspace-avatar';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { index, show } from '@/routes/projects';
import { index as decisionsIndex } from '@/routes/projects/decisions';
import { index as knowledgeIndex } from '@/routes/projects/knowledge';
import { edit } from '@/routes/projects/setup';
import type { ProjectPageProps, Task } from '@/types';

export function ProjectSidebar() {
    const { project, tree, task } = usePage<
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
            title: 'Decisions',
            href: decisionsIndex({ project: project.slug }),
            icon: Scale,
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
            </SidebarHeader>
            <SidebarContent>
                <SidebarGroup>
                    <SidebarMenu>
                        {links.map((link) => (
                            <SidebarMenuItem key={link.title}>
                                <SidebarMenuButton
                                    asChild
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

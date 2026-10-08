import { Link, usePage } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { NavUser } from '@/components/nav-user';
import { TaskTree } from '@/components/project/task-tree';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { WorkspaceAvatar } from '@/components/workspace-avatar';
import { index, show } from '@/routes/projects';
import { edit } from '@/routes/projects/setup';
import type { ProjectPageProps, Task } from '@/types';

export function ProjectSidebar() {
    const { project, tree, task } = usePage<
        ProjectPageProps & { task?: Task }
    >().props;

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

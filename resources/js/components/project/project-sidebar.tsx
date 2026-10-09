import { Link, usePage } from '@inertiajs/react';
import { ArrowLeft } from '@/components/animated-icons';
import { CommandPaletteTrigger } from '@/components/command-palette';
import { NavUser } from '@/components/nav-user';
import { TaskTree } from '@/components/project/task-tree';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarGroup,
    SidebarGroupContent,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    useSidebar,
} from '@/components/ui/sidebar';
import { WorkspaceAvatar } from '@/components/workspace-avatar';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { projectSections } from '@/lib/project-sections';
import { index, show } from '@/routes/projects';
import { edit } from '@/routes/projects/setup';
import type { ProjectPageProps, Task } from '@/types';

/**
 * Project navigation: an icon rail with the project sections, and a panel
 * with the task tree next to it.
 */
export function ProjectSidebar() {
    const { project, tree, task, pendingApprovals } = usePage<
        ProjectPageProps & { task?: Task }
    >().props;

    return (
        <Sidebar collapsible="offcanvas" variant="inset">
            <div className="flex h-full min-h-0 w-full flex-row">
                <SectionRail
                    projectSlug={project.slug}
                    projectName={project.name}
                    logoUrl={project.logoUrl}
                    pendingApprovals={pendingApprovals}
                />
                <Sidebar collapsible="none" className="min-w-0 flex-1">
                    <SidebarHeader>
                        <SidebarMenu>
                            <SidebarMenuItem>
                                <SidebarMenuButton asChild size="sm">
                                    <Link href={index()}>
                                        <ArrowLeft /> All projects
                                    </Link>
                                </SidebarMenuButton>
                            </SidebarMenuItem>
                        </SidebarMenu>
                        <div className="flex min-w-0 items-center gap-2 px-2">
                            <span className="truncate text-sm font-semibold">
                                {project.name}
                            </span>
                            <Badge variant="secondary" className="ml-auto">
                                {project.phaseLabel}
                            </Badge>
                        </div>
                        {project.setupStep && (
                            <Button variant="secondary" size="sm" asChild>
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
                        <TaskTree
                            groups={tree.groups}
                            projectSlug={project.slug}
                            activeTaskId={task?.id}
                        />
                    </SidebarContent>
                </Sidebar>
            </div>
        </Sidebar>
    );
}

function SectionRail({
    projectSlug,
    projectName,
    logoUrl,
    pendingApprovals,
}: {
    projectSlug: string;
    projectName: string;
    logoUrl: string | null;
    pendingApprovals: number;
}) {
    const { isMobile } = useSidebar();
    const { isCurrentUrl, isCurrentOrParentUrl } = useCurrentUrl();

    return (
        <Sidebar
            collapsible="none"
            className="w-[calc(var(--sidebar-width-icon)+1px)]! shrink-0 border-r"
        >
            <SidebarHeader className="items-center">
                <Link
                    href={show({ project: projectSlug })}
                    aria-label={projectName}
                    className="rounded-md outline-none focus-visible:ring-2 focus-visible:ring-ring"
                >
                    <WorkspaceAvatar name={projectName} logoUrl={logoUrl} />
                </Link>
            </SidebarHeader>
            <SidebarContent>
                <SidebarGroup className="px-1.5">
                    <SidebarGroupContent>
                        <SidebarMenu
                            aria-label="Project sections"
                            className="items-center"
                        >
                            {projectSections(projectSlug).map((section) => {
                                const active = section.exact
                                    ? isCurrentUrl(section.href)
                                    : isCurrentOrParentUrl(section.href);
                                const count =
                                    section.title === 'Approvals'
                                        ? pendingApprovals
                                        : 0;

                                return (
                                    <SidebarMenuItem key={section.title}>
                                        <SidebarMenuButton
                                            asChild
                                            isActive={active}
                                            tooltip={{
                                                children: section.title,
                                                hidden: isMobile,
                                            }}
                                            className="size-8 justify-center p-0 data-[active=true]:text-primary"
                                        >
                                            <Link
                                                href={section.href}
                                                prefetch
                                                aria-label={section.title}
                                                aria-current={
                                                    active ? 'page' : undefined
                                                }
                                            >
                                                <section.icon />
                                            </Link>
                                        </SidebarMenuButton>
                                        {count > 0 && (
                                            <span className="pointer-events-none absolute -top-1 -right-1 min-w-4 rounded-full bg-primary px-1 text-center font-mono text-[10px] leading-4 text-primary-foreground">
                                                {count}
                                            </span>
                                        )}
                                    </SidebarMenuItem>
                                );
                            })}
                        </SidebarMenu>
                    </SidebarGroupContent>
                </SidebarGroup>
            </SidebarContent>
            <SidebarFooter className="items-center">
                <NavUser compact />
            </SidebarFooter>
        </Sidebar>
    );
}

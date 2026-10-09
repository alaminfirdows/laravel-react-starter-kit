import { usePage } from '@inertiajs/react';
import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import { Breadcrumbs } from '@/components/breadcrumbs';
import { NotificationBell } from '@/components/notification-bell';
import { ProjectChannelListener } from '@/components/project/project-channel-listener';
import { ProjectProgress } from '@/components/project/project-progress';
import { ProjectSidebar } from '@/components/project/project-sidebar';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { useWorkspaceUrlDefaults } from '@/hooks/use-workspace-url-defaults';
import { index, show } from '@/routes/projects';
import { show as showTask } from '@/routes/projects/tasks';
import type { BreadcrumbItem, ProjectPageProps, Task } from '@/types';

export default function ProjectLayout({
    children,
}: {
    children: React.ReactNode;
}) {
    useWorkspaceUrlDefaults();
    const { project, tree, task } = usePage<
        ProjectPageProps & { task?: Task }
    >().props;

    const taskCrumbs = task
        ? [...task.ancestors, { id: task.id, title: task.title }].map(
              (item) => ({
                  title: item.title,
                  href: showTask({ project: project.slug, task: item.id }),
              }),
          )
        : [];

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Projects', href: index() },
        { title: project.name, href: show({ project: project.slug }) },
        ...taskCrumbs,
    ];

    return (
        <AppShell variant="sidebar">
            {import.meta.env.VITE_PUSHER_APP_KEY && (
                <ProjectChannelListener projectId={project.id} />
            )}
            <ProjectSidebar />
            <AppContent variant="sidebar" className="min-w-0 overflow-x-clip">
                <header className="flex h-16 shrink-0 items-center justify-between gap-4 border-b px-4">
                    <div className="flex min-w-0 items-center gap-2">
                        <SidebarTrigger className="-ml-1" />
                        <Breadcrumbs breadcrumbs={breadcrumbs} />
                    </div>
                    <div className="flex items-center gap-2">
                        <ProjectProgress
                            value={task ? task.progressPct : tree.progressPct}
                            label={task ? 'Task' : 'Project'}
                        />
                        <NotificationBell />
                    </div>
                </header>
                {children}
            </AppContent>
        </AppShell>
    );
}

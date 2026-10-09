import { Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowRight,
    FolderKanban,
    ListTodo,
    Package,
    Settings,
} from '@/components/animated-icons';
import { Page } from '@/components/page';
import { PageHeader } from '@/components/page-header';
import { Card } from '@/components/ui/card';
import { WorkspaceAvatar } from '@/components/workspace-avatar';
import { redirect as dashboard } from '@/routes/dashboard';
import { index as packsIndex } from '@/routes/packs';
import { index as projectsIndex } from '@/routes/projects';
import { mine as myTasks } from '@/routes/tasks';
import { edit as editWorkspaceSettings } from '@/routes/workspace/settings';

export default function Dashboard() {
    const { auth, currentWorkspace } = usePage().props;
    const firstName = auth.user.name.split(' ')[0];

    const actions = [
        {
            title: 'Projects',
            description: 'Open a company or product plan.',
            href: projectsIndex(),
            icon: FolderKanban,
        },
        {
            title: 'My tasks',
            description: 'Open tasks assigned to you.',
            href: myTasks(),
            icon: ListTodo,
        },
        {
            title: 'Packs',
            description: 'Your task bundles.',
            href: packsIndex(),
            icon: Package,
        },
        {
            title: 'Workspace settings',
            description: 'Members, billing, and details.',
            href: editWorkspaceSettings(),
            icon: Settings,
        },
    ];

    return (
        <>
            <Head title="Dashboard" />
            <Page>
                <PageHeader
                    title={`Welcome back, ${firstName}`}
                    description={
                        currentWorkspace
                            ? `You are working in ${currentWorkspace.name}.`
                            : 'Pick up where you left off.'
                    }
                    leading={
                        currentWorkspace && (
                            <WorkspaceAvatar
                                name={currentWorkspace.name}
                                logoUrl={currentWorkspace.logoUrl}
                                className="size-10"
                            />
                        )
                    }
                />
                <section className="flex flex-col gap-3">
                    <h2 className="text-sm font-semibold">Quick actions</h2>
                    <div className="grid gap-4 sm:grid-cols-2">
                        {actions.map((action) => (
                            <Link
                                key={action.title}
                                href={action.href}
                                className="group rounded-xl outline-none focus-visible:ring-2 focus-visible:ring-ring"
                            >
                                <Card size="sm" className="h-full px-(--card-spacing) transition-[border-color,box-shadow] duration-200 hover:border-border-strong hover:elev-2">
                                    <div className="flex items-center gap-3">
                                        <span className="flex size-10 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                            <action.icon className="size-5" />
                                        </span>
                                        <div className="flex min-w-0 flex-1 flex-col">
                                            <span className="text-sm font-semibold">
                                                {action.title}
                                            </span>
                                            <span className="text-sm text-muted-foreground">
                                                {action.description}
                                            </span>
                                        </div>
                                        <ArrowRight className="size-4 text-muted-foreground transition-transform group-hover:translate-x-0.5" />
                                    </div>
                                </Card>
                            </Link>
                        ))}
                    </div>
                </section>
            </Page>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
    ],
};

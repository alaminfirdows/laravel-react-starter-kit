import { Head, Link } from '@inertiajs/react';
import { FolderKanban, Plus } from '@/components/animated-icons';
import { EmptyState } from '@/components/empty-state';
import { Page } from '@/components/page';
import { PageHeader } from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Progress } from '@/components/ui/progress';
import { WorkspaceAvatar } from '@/components/workspace-avatar';
import { create, index, show } from '@/routes/projects';
import type { Project } from '@/types';

export default function ProjectsIndex({
    projects,
    can,
}: {
    projects: Project[];
    can: { create: boolean };
}) {
    const addButton = can.create && (
        <Button asChild>
            <Link href={create()}>
                <Plus /> New project
            </Link>
        </Button>
    );

    return (
        <>
            <Head title="Projects" />
            <Page>
                <PageHeader
                    title="Projects"
                    description="Each project is one company or product."
                    actions={projects.length > 0 ? addButton : undefined}
                />
                {projects.length === 0 ? (
                    <EmptyState
                        icon={FolderKanban}
                        title="No projects yet."
                        description="Create a project to get a plan for your company or product."
                        action={addButton || undefined}
                    />
                ) : (
                    <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                        {projects.map((project) => (
                            <Link
                                key={project.id}
                                href={show({ project: project.slug })}
                                className="rounded-xl outline-none focus-visible:ring-2 focus-visible:ring-ring"
                            >
                                <Card className="h-full transition-colors hover:border-primary/50 hover:bg-muted/30">
                                    <div className="flex items-center gap-3">
                                        <WorkspaceAvatar
                                            name={project.name}
                                            logoUrl={project.logoUrl}
                                            className="size-10"
                                        />
                                        <div className="flex min-w-0 flex-col">
                                            <span className="truncate text-sm font-semibold">
                                                {project.name}
                                            </span>
                                            <span className="truncate text-sm text-muted-foreground">
                                                {project.oneLiner}
                                            </span>
                                        </div>
                                    </div>
                                    <div className="mt-auto flex flex-col gap-3">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <Badge variant="primary">
                                                {project.phaseLabel}
                                            </Badge>
                                            {project.status === 'draft' && (
                                                <Badge variant="outline">
                                                    Draft
                                                </Badge>
                                            )}
                                        </div>
                                        <div className="flex items-center gap-3">
                                            <Progress
                                                value={project.progressPct ?? 0}
                                                aria-label={`${project.progressPct ?? 0}% done`}
                                            />
                                            <span className="font-mono text-xs text-muted-foreground">
                                                {project.progressPct ?? 0}%
                                            </span>
                                        </div>
                                    </div>
                                </Card>
                            </Link>
                        ))}
                    </div>
                )}
            </Page>
        </>
    );
}

ProjectsIndex.layout = { breadcrumbs: [{ title: 'Projects', href: index() }] };

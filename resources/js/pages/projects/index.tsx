import { Head, Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
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
            <div className="space-y-6 p-4 md:p-8">
                <div className="flex items-start justify-between gap-4">
                    <Heading
                        title="Projects"
                        description="Each project is one company or product."
                    />
                    {projects.length > 0 && addButton}
                </div>
                {projects.length === 0 ? (
                    <div className="flex flex-col items-center gap-4 rounded-xl border border-dashed p-12 text-center">
                        <p className="text-muted-foreground">
                            No projects yet.
                        </p>
                        {addButton}
                    </div>
                ) : (
                    <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                        {projects.map((project) => (
                            <Link
                                key={project.id}
                                href={show({ project: project.slug })}
                            >
                                <Card className="h-full hover:border-primary">
                                    <CardHeader className="flex items-center gap-3">
                                        <WorkspaceAvatar
                                            name={project.name}
                                            logoUrl={project.logoUrl}
                                            className="size-10"
                                        />
                                        <div className="min-w-0">
                                            <CardTitle className="truncate">
                                                {project.name}
                                            </CardTitle>
                                            <CardDescription className="truncate">
                                                {project.oneLiner}
                                            </CardDescription>
                                        </div>
                                    </CardHeader>
                                    <CardContent className="space-y-3">
                                        <div className="flex gap-2">
                                            <Badge>{project.phaseLabel}</Badge>
                                            {project.status === 'draft' && (
                                                <Badge variant="outline">
                                                    Draft
                                                </Badge>
                                            )}
                                        </div>
                                        <Progress
                                            value={project.progressPct ?? 0}
                                            aria-label={`${project.progressPct ?? 0}% done`}
                                        />
                                    </CardContent>
                                </Card>
                            </Link>
                        ))}
                    </div>
                )}
            </div>
        </>
    );
}

ProjectsIndex.layout = { breadcrumbs: [{ title: 'Projects', href: index() }] };

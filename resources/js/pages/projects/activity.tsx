import { Head } from '@inertiajs/react';
import { ActivityLog } from '@/components/activity/activity-log';
import Heading from '@/components/heading';
import { index } from '@/routes/projects/activity';
import type { ActivityLogProps, ProjectPageProps } from '@/types';

export default function ProjectActivity({
    project,
    ...log
}: ProjectPageProps & ActivityLogProps) {
    return (
        <>
            <Head title={`Activity · ${project.name}`} />
            <div className="mx-auto w-full max-w-4xl space-y-6 p-4 md:p-8">
                <Heading
                    title="Activity"
                    description="Who changed what, from the web, Claude (MCP) or background jobs."
                />
                <ActivityLog
                    activity={log.activity}
                    filters={log.filters}
                    options={log.options}
                    href={index({ project: project.slug })}
                />
            </div>
        </>
    );
}

import { Head } from '@inertiajs/react';
import { ActivityLog } from '@/components/activity/activity-log';
import { Page } from '@/components/page';
import { PageHeader } from '@/components/page-header';
import { index } from '@/routes/projects/activity';
import type { ActivityLogProps, ProjectPageProps } from '@/types';

export default function ProjectActivity({
    project,
    ...log
}: ProjectPageProps & ActivityLogProps) {
    return (
        <>
            <Head title={`Activity · ${project.name}`} />
            <Page>
                <PageHeader
                    title="Activity"
                    description="Who changed what, from the web, Claude (MCP) or background jobs."
                />
                <ActivityLog
                    activity={log.activity}
                    filters={log.filters}
                    options={log.options}
                    href={index({ project: project.slug })}
                />
            </Page>
        </>
    );
}

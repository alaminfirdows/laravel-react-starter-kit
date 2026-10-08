import { Head } from '@inertiajs/react';
import { ActivityLog } from '@/components/activity/activity-log';
import Heading from '@/components/heading';
import { index } from '@/routes/workspace/activity';
import type { ActivityLogProps } from '@/types';

export default function WorkspaceActivity(props: ActivityLogProps) {
    return (
        <>
            <Head title="Workspace activity" />
            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Activity"
                    description="Audit log of all projects and members in this workspace."
                />
                <ActivityLog {...props} href={index()} showProject />
            </div>
        </>
    );
}

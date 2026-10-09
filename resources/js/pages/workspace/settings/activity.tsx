import { Head } from '@inertiajs/react';
import { ActivityLog } from '@/components/activity/activity-log';
import { index } from '@/routes/workspace/activity';
import type { ActivityLogProps } from '@/types';

export default function WorkspaceActivity(props: ActivityLogProps) {
    return (
        <>
            <Head title="Workspace activity" />
            <div className="flex flex-col gap-4">
                <h2 className="text-sm font-semibold">Activity</h2>
                <p className="-mt-3 text-sm text-muted-foreground">
                    Audit log of all projects and members in this workspace.
                </p>
                <ActivityLog {...props} href={index()} showProject />
            </div>
        </>
    );
}

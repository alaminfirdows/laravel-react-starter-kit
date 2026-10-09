import { Markdown } from '@/components/markdown/markdown';
import { ActionActivity } from '@/components/task/action-activity';
import { ApprovalBanner } from '@/components/task/approval-banner';
import { PromptButtons } from '@/components/task/prompt-buttons';
import { RunInAppButton } from '@/components/task/run-in-app-button';
import { StopScheduleButton } from '@/components/task/stop-schedule-button';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import type { TaskAction } from '@/types';

export function ActionCard({
    action,
    projectSlug,
    taskId,
    canUpdate,
}: {
    action: TaskAction;
    projectSlug: string;
    taskId: string;
    canUpdate: boolean;
}) {
    const isClosed = ['done', 'skipped'].includes(action.status);
    const canStopSchedule =
        canUpdate &&
        action.isRecurring &&
        ['done', 'failed'].includes(action.status);

    return (
        <Card>
            <CardHeader>
                <CardTitle className="flex flex-wrap items-center gap-2">
                    {action.title}
                    <Badge
                        variant={action.status === 'done' ? 'success' : 'muted'}
                    >
                        {action.statusLabel}
                    </Badge>
                    <Badge variant="muted" className="font-mono">
                        {action.type}
                    </Badge>
                    <Badge variant="outline">{action.executorLabel}</Badge>
                    {action.isRequired && (
                        <Badge variant="outline">Required</Badge>
                    )}
                </CardTitle>
            </CardHeader>
            <CardContent className="flex flex-col gap-4">
                {action.pendingApproval && (
                    <ApprovalBanner
                        approval={action.pendingApproval}
                        projectSlug={projectSlug}
                        canDecide={canUpdate}
                    />
                )}
                {action.instructionsMd && (
                    <Markdown source={action.instructionsMd} />
                )}
                <ActionActivity runs={action.runs} evidence={action.evidence} />
            </CardContent>
            <CardFooter className="flex flex-wrap gap-2">
                {action.runsInApp &&
                    canUpdate &&
                    !isClosed &&
                    !action.pendingApproval && (
                        <RunInAppButton
                            action={action}
                            projectSlug={projectSlug}
                            taskId={taskId}
                        />
                    )}
                {canStopSchedule && (
                    <StopScheduleButton
                        action={action}
                        projectSlug={projectSlug}
                        taskId={taskId}
                    />
                )}
                <PromptButtons
                    prompt={action.prompt}
                    deepLink={action.deepLink}
                />
            </CardFooter>
        </Card>
    );
}

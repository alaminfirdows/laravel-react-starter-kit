import { Markdown } from '@/components/markdown/markdown';
import { ActionActivity } from '@/components/task/action-activity';
import { ApprovalBanner } from '@/components/task/approval-banner';
import { PromptButtons } from '@/components/task/prompt-buttons';
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
    canUpdate,
}: {
    action: TaskAction;
    projectSlug: string;
    canUpdate: boolean;
}) {
    return (
        <Card>
            <CardHeader>
                <CardTitle className="flex flex-wrap items-center gap-2">
                    {action.title}
                    <Badge
                        variant={
                            action.status === 'done' ? 'default' : 'secondary'
                        }
                    >
                        {action.statusLabel}
                    </Badge>
                    <Badge variant="secondary">{action.type}</Badge>
                    <Badge variant="outline">{action.executorLabel}</Badge>
                    {action.isRequired && (
                        <Badge variant="outline">Required</Badge>
                    )}
                </CardTitle>
            </CardHeader>
            <CardContent className="space-y-4">
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
            <CardFooter>
                <PromptButtons
                    prompt={action.prompt}
                    deepLink={action.deepLink}
                />
            </CardFooter>
        </Card>
    );
}

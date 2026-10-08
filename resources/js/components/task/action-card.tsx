import { Markdown } from '@/components/markdown/markdown';
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

export function ActionCard({ action }: { action: TaskAction }) {
    return (
        <Card>
            <CardHeader>
                <CardTitle className="flex flex-wrap items-center gap-2">
                    {action.title}
                    <Badge variant="secondary">{action.type}</Badge>
                    <Badge variant="outline">{action.executorLabel}</Badge>
                    {action.isRequired && (
                        <Badge variant="outline">Required</Badge>
                    )}
                </CardTitle>
            </CardHeader>
            {action.instructionsMd && (
                <CardContent>
                    <Markdown source={action.instructionsMd} />
                </CardContent>
            )}
            <CardFooter>
                <PromptButtons prompt={action.prompt} />
            </CardFooter>
        </Card>
    );
}

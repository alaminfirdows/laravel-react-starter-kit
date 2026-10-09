import { ChevronDown } from 'lucide-react';
import { Markdown } from '@/components/markdown/markdown';
import { Badge } from '@/components/ui/badge';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import type { ActionRun, Evidence } from '@/types';

function formatDate(value: string | null) {
    return value ? new Date(value).toLocaleString() : '';
}

function EvidenceItem({ evidence }: { evidence: Evidence }) {
    const isUrl =
        evidence.kind === 'url' && /^https?:\/\//i.test(evidence.value ?? '');

    return (
        <li className="flex flex-wrap items-center gap-2 text-sm">
            <Badge
                variant={evidence.passed === false ? 'destructive' : 'outline'}
            >
                {evidence.kind === 'check_result'
                    ? evidence.passed
                        ? 'check passed'
                        : 'check failed'
                    : evidence.kind}
            </Badge>
            <span className="font-medium">{evidence.label}</span>
            {isUrl ? (
                <a
                    href={evidence.value ?? undefined}
                    target="_blank"
                    rel="noreferrer"
                    className="truncate text-muted-foreground underline"
                >
                    {evidence.value}
                </a>
            ) : (
                <span className="truncate text-muted-foreground">
                    {evidence.value}
                </span>
            )}
        </li>
    );
}

function RunItem({ run }: { run: ActionRun }) {
    return (
        <li className="space-y-2 text-sm">
            <div className="flex flex-wrap items-center gap-2">
                <Badge
                    variant={
                        run.status === 'failed' ? 'destructive' : 'secondary'
                    }
                >
                    {run.status}
                </Badge>
                <span>{run.clientName ?? run.actorType}</span>
                <span className="text-muted-foreground">
                    {formatDate(run.startedAt)}
                </span>
                {run.usage && (
                    <span className="text-muted-foreground">
                        {run.usage.model} ·{' '}
                        {run.usage.totalTokens.toLocaleString()} tokens
                    </span>
                )}
            </div>
            {run.error && <p className="text-destructive">{run.error}</p>}
            {run.outputMd && (
                <div className="rounded-md bg-muted p-3">
                    <Markdown source={run.outputMd} />
                </div>
            )}
        </li>
    );
}

export function ActionActivity({
    runs,
    evidence,
}: {
    runs: ActionRun[];
    evidence: Evidence[];
}) {
    if (runs.length === 0 && evidence.length === 0) {
        return null;
    }

    return (
        <Collapsible className="w-full space-y-3">
            <CollapsibleTrigger className="flex items-center gap-1 text-sm text-muted-foreground">
                <ChevronDown className="size-4" />
                {runs.length} runs · {evidence.length} evidence
            </CollapsibleTrigger>
            <CollapsibleContent className="space-y-4">
                {evidence.length > 0 && (
                    <ul className="space-y-1">
                        {evidence.map((item) => (
                            <EvidenceItem key={item.id} evidence={item} />
                        ))}
                    </ul>
                )}
                {runs.length > 0 && (
                    <ul className="space-y-3">
                        {runs.map((run) => (
                            <RunItem key={run.id} run={run} />
                        ))}
                    </ul>
                )}
            </CollapsibleContent>
        </Collapsible>
    );
}

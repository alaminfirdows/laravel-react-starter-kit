import { Progress } from '@/components/ui/progress';

export function ProjectProgress({
    value,
    label = 'Progress',
}: {
    value: number;
    label?: string;
}) {
    return (
        <div
            className="flex items-center gap-3"
            aria-label={`${label}: ${value}%`}
        >
            <span className="hidden text-xs text-muted-foreground sm:inline">
                {label}
            </span>
            <Progress value={value} className="h-1.5 w-20 md:w-28" />
            <span className="w-9 text-right font-mono text-xs font-medium text-muted-foreground tabular-nums">
                {value}%
            </span>
        </div>
    );
}

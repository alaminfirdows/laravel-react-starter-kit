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
            <Progress value={value} className="w-32 md:w-40" />
            <span className="w-10 text-right text-sm font-medium tabular-nums">
                {value}%
            </span>
        </div>
    );
}

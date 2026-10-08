import {
    CheckCircle2,
    Circle,
    CircleDot,
    CircleSlash,
    Lock,
} from 'lucide-react';
import { cn } from '@/lib/utils';
import type { TaskStatus } from '@/types';

const icons: Partial<Record<TaskStatus, [typeof Circle, string]>> = {
    done: [CheckCircle2, 'text-emerald-600'],
    in_progress: [CircleDot, 'text-amber-500'],
    locked: [Lock, 'text-muted-foreground'],
    skipped: [CircleSlash, 'text-muted-foreground'],
};

export function TaskStatusIcon({
    status,
    className,
}: {
    status: TaskStatus;
    className?: string;
}) {
    const [Icon, color] = icons[status] ?? [Circle, 'text-muted-foreground'];

    return (
        <Icon
            aria-label={status.replace('_', ' ')}
            className={cn('size-4 shrink-0', color, className)}
        />
    );
}

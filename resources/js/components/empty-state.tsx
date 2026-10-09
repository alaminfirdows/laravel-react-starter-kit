import type { AppIcon } from '@/components/animated-icons';
import type { ReactNode } from 'react';
import {
    Empty,
    EmptyContent,
    EmptyDescription,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from '@/components/ui/empty';
import { cn } from '@/lib/utils';

/**
 * Empty placeholder for lists and sections: icon, title, hint, and action.
 */
export function EmptyState({
    icon: Icon,
    title,
    description,
    action,
    size = 'default',
    className,
}: {
    icon?: AppIcon;
    title: ReactNode;
    description?: ReactNode;
    action?: ReactNode;
    size?: 'default' | 'sm';
    className?: string;
}) {
    return (
        <Empty
            className={cn(
                'border bg-card/50',
                size === 'sm' && 'gap-3 p-6 md:p-8',
                className,
            )}
        >
            <EmptyHeader>
                {Icon && (
                    <EmptyMedia variant="icon">
                        <Icon />
                    </EmptyMedia>
                )}
                <EmptyTitle>{title}</EmptyTitle>
                {description && (
                    <EmptyDescription>{description}</EmptyDescription>
                )}
            </EmptyHeader>
            {action && <EmptyContent>{action}</EmptyContent>}
        </Empty>
    );
}

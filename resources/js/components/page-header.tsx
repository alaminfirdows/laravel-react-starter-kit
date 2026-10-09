import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

/**
 * Page title row: title, optional meta line, and right-aligned actions.
 */
export function PageHeader({
    title,
    description,
    leading,
    meta,
    actions,
    className,
}: {
    title: ReactNode;
    description?: ReactNode;
    leading?: ReactNode;
    meta?: ReactNode;
    actions?: ReactNode;
    className?: string;
}) {
    return (
        <header
            className={cn(
                'flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between',
                className,
            )}
        >
            <div className="flex min-w-0 items-start gap-3">
                {leading}
                <div className="flex min-w-0 flex-col gap-1">
                    <h1 className="truncate text-xl font-semibold tracking-tight text-foreground">
                        {title}
                    </h1>
                    {description && (
                        <p className="text-sm text-pretty text-muted-foreground">
                            {description}
                        </p>
                    )}
                    {meta && (
                        <div className="mt-1 flex flex-wrap items-center gap-2">
                            {meta}
                        </div>
                    )}
                </div>
            </div>
            {actions && (
                <div className="flex shrink-0 flex-wrap items-center gap-2">
                    {actions}
                </div>
            )}
        </header>
    );
}

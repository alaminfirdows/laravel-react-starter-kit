import type { ComponentProps, ReactNode } from 'react';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { cn } from '@/lib/utils';

/**
 * Settings group: Card with title/description header. Compose with
 * SettingsCardBody and SettingsCardFooter (inside a Form when needed).
 */
export function SettingsCard({
    title,
    description,
    destructive = false,
    className,
    children,
    ...props
}: Omit<ComponentProps<typeof Card>, 'title'> & {
    title: ReactNode;
    description?: ReactNode;
    destructive?: boolean;
}) {
    return (
        <Card
            className={cn(
                'gap-0 py-0',
                destructive && 'border-destructive/30',
                className,
            )}
            {...props}
        >
            <CardHeader className="gap-1.5 px-5 pt-5 pb-4">
                <CardTitle className={cn(destructive && 'text-destructive')}>
                    {title}
                </CardTitle>
                {description && (
                    <CardDescription>{description}</CardDescription>
                )}
            </CardHeader>
            {children}
        </Card>
    );
}

export function SettingsCardBody({
    className,
    ...props
}: ComponentProps<typeof CardContent>) {
    return (
        <CardContent
            className={cn('flex flex-col gap-5 px-5 pb-5', className)}
            {...props}
        />
    );
}

export function SettingsCardFooter({
    helper,
    children,
    className,
}: {
    helper?: ReactNode;
    children?: ReactNode;
    className?: string;
}) {
    return (
        <div
            className={cn(
                'flex flex-wrap items-center justify-between gap-3 border-t bg-muted/40 px-5 py-3',
                className,
            )}
        >
            <p className="text-xs text-muted-foreground">{helper}</p>
            {children && (
                <div className="flex items-center gap-2">{children}</div>
            )}
        </div>
    );
}

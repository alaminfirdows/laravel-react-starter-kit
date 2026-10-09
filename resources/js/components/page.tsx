import type { ComponentProps } from 'react';
import { cn } from '@/lib/utils';

/**
 * Standard page container: centered column with consistent gutters.
 */
export function Page({
    className,
    size = 'default',
    ...props
}: ComponentProps<'div'> & { size?: 'default' | 'narrow' | 'wide' }) {
    return (
        <div
            className={cn(
                'mx-auto flex w-full flex-col gap-6 px-4 py-6 md:px-8 md:py-8',
                size === 'narrow' && 'max-w-3xl',
                size === 'default' && 'max-w-6xl',
                size === 'wide' && 'max-w-7xl',
                className,
            )}
            {...props}
        />
    );
}

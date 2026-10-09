import { Skeleton } from '@/components/ui/skeleton';

/**
 * Loading placeholder for row lists and card grids (deferred props).
 */
export function ListSkeleton({
    rows = 4,
    variant = 'rows',
}: {
    rows?: number;
    variant?: 'rows' | 'cards';
}) {
    if (variant === 'cards') {
        return (
            <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                {Array.from({ length: rows }, (_, index) => (
                    <div
                        key={index}
                        className="flex flex-col gap-3 rounded-lg border bg-card p-5"
                    >
                        <div className="flex items-center gap-3">
                            <Skeleton className="size-9" />
                            <div className="flex flex-1 flex-col gap-2">
                                <Skeleton className="h-3.5 w-2/3" />
                                <Skeleton className="h-3 w-1/2" />
                            </div>
                        </div>
                        <Skeleton className="h-1.5 w-full" />
                    </div>
                ))}
            </div>
        );
    }

    return (
        <div className="divide-y rounded-lg border bg-card">
            {Array.from({ length: rows }, (_, index) => (
                <div key={index} className="flex items-center gap-3 px-4 py-3">
                    <Skeleton className="size-4 rounded-full" />
                    <Skeleton className="h-3.5 flex-1" />
                    <Skeleton className="h-3.5 w-16" />
                </div>
            ))}
        </div>
    );
}

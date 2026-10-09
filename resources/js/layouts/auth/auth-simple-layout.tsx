import { Link } from '@inertiajs/react';
import AppLogoIcon from '@/components/app-logo-icon';
import { home } from '@/routes';
import type { AuthLayoutProps } from '@/types';

export default function AuthSimpleLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    return (
        <div className="relative isolate flex min-h-svh flex-col items-center justify-center bg-background p-6 md:p-10">
            <div
                aria-hidden
                className="pointer-events-none absolute inset-0 -z-10 bg-[radial-gradient(60%_50%_at_50%_0%,color-mix(in_oklab,var(--primary)_14%,transparent),transparent_70%)]"
            />
            <div
                aria-hidden
                className="pointer-events-none absolute inset-0 -z-10 bg-[linear-gradient(to_right,var(--border)_1px,transparent_1px),linear-gradient(to_bottom,var(--border)_1px,transparent_1px)] [mask-image:radial-gradient(50%_50%_at_50%_30%,black,transparent_75%)] bg-[size:48px_48px] opacity-30"
            />
            <div className="flex w-full max-w-sm flex-col gap-8">
                <div className="flex flex-col items-center gap-5">
                    <Link
                        href={home()}
                        className="flex size-10 items-center justify-center rounded-xl bg-primary text-primary-foreground shadow-xs"
                    >
                        <AppLogoIcon className="size-6 fill-current" />
                        <span className="sr-only">{title}</span>
                    </Link>
                    <div className="flex flex-col gap-1.5 text-center">
                        <h1 className="text-xl font-semibold tracking-tight">
                            {title}
                        </h1>
                        <p className="text-sm text-balance text-muted-foreground">
                            {description}
                        </p>
                    </div>
                </div>
                <div className="rounded-xl border bg-card p-6 text-card-foreground shadow-xs">
                    {children}
                </div>
            </div>
        </div>
    );
}

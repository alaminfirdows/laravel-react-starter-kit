import { Link } from '@inertiajs/react';
import type { PropsWithChildren } from 'react';
import AppLogoIcon from '@/components/app-logo-icon';
import { home } from '@/routes';

export default function AuthCardLayout({
    children,
    title,
    description,
}: PropsWithChildren<{
    name?: string;
    title?: string;
    description?: string;
}>) {
    return (
        <div className="relative isolate flex min-h-svh flex-col items-center justify-center bg-background p-6 md:p-10">
            <div
                aria-hidden
                className="pointer-events-none absolute inset-0 -z-10 bg-[radial-gradient(60%_50%_at_50%_0%,color-mix(in_oklab,var(--primary)_14%,transparent),transparent_70%)]"
            />
            <div className="flex w-full max-w-sm flex-col gap-6">
                <Link
                    href={home()}
                    className="flex size-10 items-center justify-center self-center rounded-xl bg-primary text-primary-foreground shadow-xs"
                >
                    <AppLogoIcon className="size-6 fill-current" />
                </Link>
                <div className="flex flex-col gap-6 rounded-xl border bg-card p-6 text-card-foreground shadow-xs">
                    <div className="flex flex-col gap-1.5 text-center">
                        <h1 className="text-xl font-semibold tracking-tight">
                            {title}
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {description}
                        </p>
                    </div>
                    {children}
                </div>
            </div>
        </div>
    );
}

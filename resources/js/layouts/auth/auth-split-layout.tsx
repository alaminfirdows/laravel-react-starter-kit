import { Link, usePage } from '@inertiajs/react';
import AppLogoIcon from '@/components/app-logo-icon';
import { home } from '@/routes';
import type { AuthLayoutProps } from '@/types';

export default function AuthSplitLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    const { name } = usePage().props;

    return (
        <div className="relative grid h-dvh items-center bg-background lg:grid-cols-2">
            <div className="relative hidden h-full flex-col border-r bg-muted/40 p-10 lg:flex">
                <div
                    aria-hidden
                    className="pointer-events-none absolute inset-0 bg-[radial-gradient(60%_50%_at_30%_0%,color-mix(in_oklab,var(--primary)_16%,transparent),transparent_70%)]"
                />
                <Link
                    href={home()}
                    className="relative z-20 flex items-center gap-2.5 text-sm font-semibold"
                >
                    <span className="flex size-8 items-center justify-center rounded-lg bg-primary text-primary-foreground shadow-xs">
                        <AppLogoIcon className="size-5 fill-current" />
                    </span>
                    {name}
                </Link>
            </div>
            <div className="w-full px-6 lg:p-8">
                <div className="mx-auto flex w-full flex-col justify-center gap-6 sm:w-[350px]">
                    <Link
                        href={home()}
                        className="flex size-10 items-center justify-center self-center rounded-xl bg-primary text-primary-foreground shadow-xs lg:hidden"
                    >
                        <AppLogoIcon className="size-6 fill-current" />
                    </Link>
                    <div className="flex flex-col gap-1.5 text-center">
                        <h1 className="text-xl font-semibold tracking-tight">
                            {title}
                        </h1>
                        <p className="text-sm text-balance text-muted-foreground">
                            {description}
                        </p>
                    </div>
                    {children}
                </div>
            </div>
        </div>
    );
}

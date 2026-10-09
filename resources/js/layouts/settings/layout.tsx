import { Link } from '@inertiajs/react';
import type { PropsWithChildren } from 'react';
import { Page } from '@/components/page';
import { PageHeader } from '@/components/page-header';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { cn, toUrl } from '@/lib/utils';
import { edit as editAppearance } from '@/routes/appearance';
import { edit as editConnectClaude } from '@/routes/connect-claude';
import { edit } from '@/routes/profile';
import { edit as editSecurity } from '@/routes/security';
import type { NavItem } from '@/types';

const sidebarNavItems: NavItem[] = [
    {
        title: 'Profile',
        href: edit(),
        icon: null,
    },
    {
        title: 'Security',
        href: editSecurity(),
        icon: null,
    },
    {
        title: 'Appearance',
        href: editAppearance(),
        icon: null,
    },
    {
        title: 'Connect Claude',
        href: editConnectClaude(),
        icon: null,
    },
];

export default function SettingsLayout({ children }: PropsWithChildren) {
    const { isCurrentOrParentUrl } = useCurrentUrl();

    return (
        <Page size="narrow" className="max-w-4xl">
            <PageHeader
                title="Settings"
                description="Manage your profile and account settings"
            />

            <div className="flex flex-col gap-6 md:flex-row md:gap-10">
                <nav
                    className="-mx-4 flex gap-1 overflow-x-auto px-4 md:mx-0 md:w-44 md:shrink-0 md:flex-col md:overflow-visible md:px-0"
                    aria-label="Settings"
                >
                    {sidebarNavItems.map((item, index) => (
                        <Link
                            key={`${toUrl(item.href)}-${index}`}
                            href={item.href}
                            className={cn(
                                'shrink-0 rounded-md px-3 py-1.5 text-sm whitespace-nowrap transition-colors',
                                isCurrentOrParentUrl(item.href)
                                    ? 'bg-muted font-medium text-foreground'
                                    : 'text-muted-foreground hover:bg-muted/50 hover:text-foreground',
                            )}
                        >
                            {item.title}
                        </Link>
                    ))}
                </nav>

                <div className="flex max-w-2xl min-w-0 flex-1 flex-col gap-6">
                    {children}
                </div>
            </div>
        </Page>
    );
}

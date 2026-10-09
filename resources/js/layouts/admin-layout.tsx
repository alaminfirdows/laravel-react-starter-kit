import { Link } from '@inertiajs/react';
import type { PropsWithChildren } from 'react';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { cn, toUrl } from '@/lib/utils';
import { index } from '@/routes/admin';
import { index as catalogIndex } from '@/routes/admin/catalog';
import { index as communityPacksIndex } from '@/routes/admin/community-packs';
import { index as packsIndex } from '@/routes/admin/packs';
import { index as promptsIndex } from '@/routes/admin/prompts';
import type { NavItem } from '@/types';

export default function AdminLayout({ children }: PropsWithChildren) {
    const { isCurrentUrl, isCurrentOrParentUrl } = useCurrentUrl();

    const navItems: NavItem[] = [
        { title: 'Overview', href: index() },
        { title: 'Catalog', href: catalogIndex() },
        { title: 'Prompts', href: promptsIndex() },
        { title: 'Packs', href: packsIndex() },
        { title: 'Community', href: communityPacksIndex() },
    ];

    return (
        <div className="flex flex-col">
            <nav
                aria-label="Admin"
                className="flex shrink-0 [scrollbar-width:none] gap-1 overflow-x-auto border-b px-3 md:px-4"
            >
                <span className="flex h-10 shrink-0 items-center pr-3 pl-2.5 text-sm font-semibold">
                    Admin
                </span>
                {navItems.map((item, position) => {
                    const active =
                        position === 0
                            ? isCurrentUrl(item.href)
                            : isCurrentOrParentUrl(item.href);

                    return (
                        <Link
                            key={toUrl(item.href)}
                            href={item.href}
                            prefetch
                            aria-current={active ? 'page' : undefined}
                            className={cn(
                                'relative flex h-10 shrink-0 items-center px-2.5 text-sm font-medium text-muted-foreground transition-colors hover:text-foreground',
                                'after:absolute after:inset-x-2 after:bottom-[-1px] after:h-0.5 after:rounded-full after:bg-transparent after:transition-colors',
                                active && 'text-foreground after:bg-primary',
                            )}
                        >
                            {item.title}
                        </Link>
                    );
                })}
            </nav>

            <div className="min-w-0 flex-1">{children}</div>
        </div>
    );
}

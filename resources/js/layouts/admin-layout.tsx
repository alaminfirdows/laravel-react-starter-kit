import { Link } from '@inertiajs/react';
import type { PropsWithChildren } from 'react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { cn, toUrl } from '@/lib/utils';
import { index } from '@/routes/admin';
import { index as catalogIndex } from '@/routes/admin/catalog';
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
    ];

    return (
        <div className="px-4 py-6">
            <Heading
                title="Admin"
                description="Catalog authoring, pack review and analytics"
            />

            <div className="flex flex-col lg:flex-row lg:space-x-12">
                <aside className="w-full max-w-xl lg:w-48">
                    <nav
                        className="flex flex-col space-y-1 space-x-0"
                        aria-label="Admin"
                    >
                        {navItems.map((item, position) => (
                            <Button
                                key={toUrl(item.href)}
                                size="sm"
                                variant="ghost"
                                asChild
                                className={cn('w-full justify-start', {
                                    'bg-muted':
                                        position === 0
                                            ? isCurrentUrl(item.href)
                                            : isCurrentOrParentUrl(item.href),
                                })}
                            >
                                <Link href={item.href}>{item.title}</Link>
                            </Button>
                        ))}
                    </nav>
                </aside>

                <Separator className="my-6 lg:hidden" />

                <div className="min-w-0 flex-1">{children}</div>
            </div>
        </div>
    );
}

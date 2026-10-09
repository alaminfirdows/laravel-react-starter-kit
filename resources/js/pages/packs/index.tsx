import { Head, Link } from '@inertiajs/react';
import { Package, Plus } from '@/components/animated-icons';
import { EmptyState } from '@/components/empty-state';
import { Page } from '@/components/page';
import { PageHeader } from '@/components/page-header';
import { PackReviewBadge } from '@/components/pack/pack-review-badge';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { create, edit } from '@/routes/packs';
import type { CommunityPack } from '@/types';

export default function CommunityPacksIndex({
    packs,
    can,
}: {
    packs: CommunityPack[];
    can: { create: boolean };
}) {
    const newButton = can.create && (
        <Button asChild>
            <Link href={create()}>
                <Plus /> New pack
            </Link>
        </Button>
    );

    return (
        <>
            <Head title="Packs" />
            <Page>
                <PageHeader
                    title="Packs"
                    description="Your own task bundles. Add them to projects, or share them with everyone after review."
                    actions={packs.length > 0 ? newButton : undefined}
                />
                {packs.length === 0 ? (
                    <EmptyState
                        icon={Package}
                        title="No packs yet."
                        description="Bundle catalog tasks into a pack you can reuse."
                        action={newButton || undefined}
                    />
                ) : (
                    <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                        {packs.map((pack) => (
                            <Link
                                key={pack.key}
                                href={edit({ pack: pack.key })}
                                className="rounded-xl outline-none focus-visible:ring-2 focus-visible:ring-ring"
                            >
                                <Card className="h-full transition-colors hover:border-primary/50 hover:bg-muted/30">
                                    <span className="truncate text-sm font-semibold">
                                        {pack.name}
                                    </span>
                                    <div className="mt-auto flex flex-wrap items-center gap-2">
                                        {pack.phase && (
                                            <Badge
                                                variant="outline"
                                                className="capitalize"
                                            >
                                                {pack.phase}
                                            </Badge>
                                        )}
                                        <PackReviewBadge pack={pack} />
                                        <span className="ml-auto font-mono text-xs text-muted-foreground">
                                            {pack.itemsCount} tasks
                                        </span>
                                    </div>
                                </Card>
                            </Link>
                        ))}
                    </div>
                )}
            </Page>
        </>
    );
}

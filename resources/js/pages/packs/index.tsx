import { Head, Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import Heading from '@/components/heading';
import { PackReviewBadge } from '@/components/pack/pack-review-badge';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { create, edit } from '@/routes/packs';
import type { CommunityPack } from '@/types';

export default function CommunityPacksIndex({
    packs,
    can,
}: {
    packs: CommunityPack[];
    can: { create: boolean };
}) {
    return (
        <>
            <Head title="Packs" />
            <div className="space-y-6 p-4 md:p-8">
                <div className="flex items-start justify-between gap-4">
                    <Heading
                        title="Packs"
                        description="Your own task bundles. Add them to projects, or share them with everyone after review."
                    />
                    {can.create && (
                        <Button asChild>
                            <Link href={create()}>
                                <Plus /> New pack
                            </Link>
                        </Button>
                    )}
                </div>
                {packs.length === 0 ? (
                    <div className="rounded-xl border border-dashed p-12 text-center text-muted-foreground">
                        No packs yet.
                    </div>
                ) : (
                    <Card>
                        <CardContent>
                            <ul className="divide-y">
                                {packs.map((pack) => (
                                    <li
                                        key={pack.key}
                                        className="flex items-center gap-2 py-2"
                                    >
                                        <Link
                                            href={edit({ pack: pack.key })}
                                            className="min-w-0 flex-1 truncate text-sm hover:underline"
                                        >
                                            {pack.name}
                                        </Link>
                                        <span className="text-xs text-muted-foreground">
                                            {pack.itemsCount} tasks
                                        </span>
                                        {pack.phase && (
                                            <Badge
                                                variant="outline"
                                                className="capitalize"
                                            >
                                                {pack.phase}
                                            </Badge>
                                        )}
                                        <PackReviewBadge pack={pack} />
                                    </li>
                                ))}
                            </ul>
                        </CardContent>
                    </Card>
                )}
            </div>
        </>
    );
}

import { Head, Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { CatalogStatusBadge } from '@/components/admin/catalog-status-badge';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { create, edit } from '@/routes/admin/packs';
import type { AdminPack } from '@/types';

export default function PacksIndex({ packs }: { packs: AdminPack[] }) {
    return (
        <>
            <Head title="Packs" />
            <div className="space-y-6">
                <div className="flex items-start justify-between gap-4">
                    <Heading
                        variant="small"
                        title="Packs"
                        description="Task bundles applied to projects by phase."
                    />
                    <Button size="sm" asChild>
                        <Link href={create()}>
                            <Plus /> New pack
                        </Link>
                    </Button>
                </div>
                <Card>
                    <CardContent>
                        {packs.length > 0 ? (
                            <ul className="divide-y">
                                {packs.map((pack) => (
                                    <li
                                        key={pack.key}
                                        className="flex items-center gap-2 py-2"
                                    >
                                        <Link
                                            href={edit(pack.key)}
                                            className="min-w-0 flex-1 truncate text-sm hover:underline"
                                        >
                                            {pack.name}
                                        </Link>
                                        <span className="text-xs text-muted-foreground">
                                            {pack.itemsCount} tasks · v
                                            {pack.version}
                                        </span>
                                        {pack.isDefault && (
                                            <Badge>Default</Badge>
                                        )}
                                        {pack.phase && (
                                            <Badge
                                                variant="outline"
                                                className="capitalize"
                                            >
                                                {pack.phase}
                                            </Badge>
                                        )}
                                        <CatalogStatusBadge
                                            status={pack.status}
                                        />
                                    </li>
                                ))}
                            </ul>
                        ) : (
                            <p className="text-sm text-muted-foreground">
                                No packs yet.
                            </p>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

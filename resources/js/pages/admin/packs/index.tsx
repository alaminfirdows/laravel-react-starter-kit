import { Head, Link } from '@inertiajs/react';
import { Package, Plus } from '@/components/animated-icons';
import { CatalogStatusBadge } from '@/components/admin/catalog-status-badge';
import { EmptyState } from '@/components/empty-state';
import { Page } from '@/components/page';
import { PageHeader } from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { create, edit } from '@/routes/admin/packs';
import type { AdminPack } from '@/types';

export default function PacksIndex({ packs }: { packs: AdminPack[] }) {
    const newPack = (
        <Button variant="primary" size="sm" asChild>
            <Link href={create()}>
                <Plus data-icon="inline-start" /> New pack
            </Link>
        </Button>
    );

    return (
        <>
            <Head title="Packs" />
            <Page>
                <PageHeader
                    title="Packs"
                    description="Task bundles applied to projects by phase."
                    actions={newPack}
                />
                {packs.length > 0 ? (
                    <Card className="gap-0 py-0">
                        <Table>
                            <TableHeader>
                                <TableRow className="hover:bg-transparent">
                                    <TableHead className="pl-4">Pack</TableHead>
                                    <TableHead>Phase</TableHead>
                                    <TableHead>Tasks</TableHead>
                                    <TableHead>Version</TableHead>
                                    <TableHead className="pr-4 text-right">
                                        Status
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {packs.map((pack) => (
                                    <TableRow key={pack.key}>
                                        <TableCell className="py-2 pl-4">
                                            <div className="flex items-center gap-2">
                                                <Link
                                                    href={edit(pack.key)}
                                                    className="min-w-0 truncate font-medium hover:underline"
                                                >
                                                    {pack.name}
                                                </Link>
                                                {pack.isDefault && (
                                                    <Badge variant="primary">
                                                        Default
                                                    </Badge>
                                                )}
                                            </div>
                                        </TableCell>
                                        <TableCell className="py-2">
                                            {pack.phase && (
                                                <Badge
                                                    variant="outline"
                                                    className="capitalize"
                                                >
                                                    {pack.phase}
                                                </Badge>
                                            )}
                                        </TableCell>
                                        <TableCell className="py-2 font-mono text-xs text-muted-foreground">
                                            {pack.itemsCount} tasks
                                        </TableCell>
                                        <TableCell className="py-2 font-mono text-xs text-muted-foreground">
                                            v{pack.version}
                                        </TableCell>
                                        <TableCell className="py-2 pr-4 text-right">
                                            <CatalogStatusBadge
                                                status={pack.status}
                                            />
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </Card>
                ) : (
                    <EmptyState
                        icon={Package}
                        title="No packs yet."
                        description="Bundle tasks into a pack to apply them to projects."
                        action={newPack}
                    />
                )}
            </Page>
        </>
    );
}

import { Head, Link } from '@inertiajs/react';
import { ListTree, Plus } from '@/components/animated-icons';
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
import { create, edit } from '@/routes/admin/catalog';
import type { CatalogTreeCategory, CatalogTreeNode } from '@/types';

function TreeRows({ node, depth }: { node: CatalogTreeNode; depth: number }) {
    return (
        <>
            <TableRow>
                <TableCell
                    className="py-2 pr-2"
                    style={{ paddingLeft: 16 + depth * 20 }}
                >
                    <div className="flex items-center gap-2">
                        <Link
                            href={edit(node.key)}
                            className="min-w-0 truncate font-medium hover:underline"
                        >
                            {node.title}
                        </Link>
                        {node.isEdited && (
                            <Badge variant="warning">Edited</Badge>
                        )}
                    </div>
                </TableCell>
                <TableCell className="w-16 py-2 font-mono text-xs text-muted-foreground">
                    v{node.version}
                </TableCell>
                <TableCell className="w-28 py-2 pr-4 text-right">
                    <CatalogStatusBadge status={node.status} />
                </TableCell>
            </TableRow>
            {node.children.map((child) => (
                <TreeRows key={child.key} node={child} depth={depth + 1} />
            ))}
        </>
    );
}

export default function CatalogIndex({
    categories,
}: {
    categories: CatalogTreeCategory[];
}) {
    return (
        <>
            <Head title="Catalog" />
            <Page>
                <PageHeader
                    title="Catalog"
                    description="“Edited” rows are not yet exported to YAML."
                    actions={
                        <Button size="sm" asChild>
                            <Link href={create()}>
                                <Plus data-icon="inline-start" /> New task
                            </Link>
                        </Button>
                    }
                />
                {categories.map((category) => (
                    <section key={category.id} className="flex flex-col gap-2">
                        <h2 className="flex items-center gap-2 text-sm font-semibold">
                            {category.name}
                            <Badge variant="outline" className="capitalize">
                                {category.phase}
                            </Badge>
                        </h2>
                        {category.tasks.length > 0 ? (
                            <Card className="gap-0 py-0">
                                <Table>
                                    <TableHeader>
                                        <TableRow className="hover:bg-transparent">
                                            <TableHead className="pl-4">
                                                Task
                                            </TableHead>
                                            <TableHead>Version</TableHead>
                                            <TableHead className="pr-4 text-right">
                                                Status
                                            </TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {category.tasks.map((task) => (
                                            <TreeRows
                                                key={task.key}
                                                node={task}
                                                depth={0}
                                            />
                                        ))}
                                    </TableBody>
                                </Table>
                            </Card>
                        ) : (
                            <EmptyState
                                icon={ListTree}
                                size="sm"
                                title="No tasks yet."
                            />
                        )}
                    </section>
                ))}
            </Page>
        </>
    );
}

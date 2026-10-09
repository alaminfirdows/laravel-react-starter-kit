import { Head, Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { CatalogStatusBadge } from '@/components/admin/catalog-status-badge';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { create, edit } from '@/routes/admin/catalog';
import type { CatalogTreeCategory, CatalogTreeNode } from '@/types';

function TreeRow({ node, depth }: { node: CatalogTreeNode; depth: number }) {
    return (
        <li>
            <div
                className="flex items-center gap-2 py-1.5"
                style={{ paddingLeft: depth * 20 }}
            >
                <Link
                    href={edit(node.key)}
                    className="min-w-0 flex-1 truncate text-sm hover:underline"
                >
                    {node.title}
                </Link>
                <span className="text-xs text-muted-foreground">
                    v{node.version}
                </span>
                {node.isEdited && <Badge variant="outline">Edited</Badge>}
                <CatalogStatusBadge status={node.status} />
            </div>
            {node.children.length > 0 && (
                <ul>
                    {node.children.map((child) => (
                        <TreeRow
                            key={child.key}
                            node={child}
                            depth={depth + 1}
                        />
                    ))}
                </ul>
            )}
        </li>
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
            <div className="space-y-6">
                <div className="flex items-start justify-between gap-4">
                    <Heading
                        variant="small"
                        title="Catalog"
                        description="“Edited” rows are not yet exported to YAML."
                    />
                    <Button size="sm" asChild>
                        <Link href={create()}>
                            <Plus /> New task
                        </Link>
                    </Button>
                </div>
                {categories.map((category) => (
                    <Card key={category.id}>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                {category.name}
                                <Badge variant="outline">
                                    {category.phase}
                                </Badge>
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            {category.tasks.length > 0 ? (
                                <ul className="divide-y">
                                    {category.tasks.map((task) => (
                                        <TreeRow
                                            key={task.key}
                                            node={task}
                                            depth={0}
                                        />
                                    ))}
                                </ul>
                            ) : (
                                <p className="text-sm text-muted-foreground">
                                    No tasks yet.
                                </p>
                            )}
                        </CardContent>
                    </Card>
                ))}
            </div>
        </>
    );
}

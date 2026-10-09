import { Badge } from '@/components/ui/badge';
import type { CatalogStatus } from '@/types';

const variants: Record<CatalogStatus, 'default' | 'secondary' | 'outline'> = {
    published: 'default',
    draft: 'secondary',
    archived: 'outline',
};

export function CatalogStatusBadge({ status }: { status: CatalogStatus }) {
    return (
        <Badge variant={variants[status]} className="capitalize">
            {status}
        </Badge>
    );
}

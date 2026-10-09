import { Badge } from '@/components/ui/badge';
import type { CatalogStatus } from '@/types';

const variants: Record<CatalogStatus, 'success' | 'muted' | 'outline'> = {
    published: 'success',
    draft: 'muted',
    archived: 'outline',
};

export function CatalogStatusBadge({ status }: { status: CatalogStatus }) {
    return (
        <Badge variant={variants[status]} className="capitalize">
            {status}
        </Badge>
    );
}

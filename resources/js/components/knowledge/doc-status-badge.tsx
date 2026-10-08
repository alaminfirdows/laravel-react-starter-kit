import { Badge } from '@/components/ui/badge';
import type { DocStatus } from '@/types';

const variants = {
    draft: 'outline',
    approved: 'default',
    archived: 'secondary',
} as const;

export function DocStatusBadge({ status }: { status: DocStatus }) {
    return (
        <Badge variant={variants[status]} className="capitalize">
            {status}
        </Badge>
    );
}

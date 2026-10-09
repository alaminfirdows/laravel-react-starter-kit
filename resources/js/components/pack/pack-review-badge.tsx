import { Badge } from '@/components/ui/badge';
import type { CommunityPack } from '@/types';

export function PackReviewBadge({ pack }: { pack: CommunityPack }) {
    if (pack.visibility === 'private') {
        return <Badge variant="outline">Private</Badge>;
    }

    switch (pack.reviewStatus) {
        case 'approved':
            return <Badge>Public</Badge>;
        case 'rejected':
            return <Badge variant="destructive">Rejected</Badge>;
        default:
            return <Badge variant="secondary">In review</Badge>;
    }
}

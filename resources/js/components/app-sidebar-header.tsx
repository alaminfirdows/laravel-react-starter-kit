import { AppTopbar } from '@/components/app-topbar';
import type { BreadcrumbItem as BreadcrumbItemType } from '@/types';

export function AppSidebarHeader({
    breadcrumbs = [],
}: {
    breadcrumbs?: BreadcrumbItemType[];
}) {
    return <AppTopbar breadcrumbs={breadcrumbs} />;
}

import type { InertiaLinkProps } from '@inertiajs/react';
import type { AppIcon } from '@/components/animated-icons';

export type BreadcrumbItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
};

export type NavItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
    icon?: AppIcon | null;
    isActive?: boolean;
};

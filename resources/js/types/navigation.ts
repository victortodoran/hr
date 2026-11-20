import type { InertiaLinkProps } from '@inertiajs/vue3';
import type { LucideIcon } from '@lucide/vue';

export type BreadcrumbItem = {
    title: string;
    /** Where it leads; left out for a heading with no page of its own. */
    href?: NonNullable<InertiaLinkProps['href']>;
};

export type NavItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
    icon?: LucideIcon;
    isActive?: boolean;
    /** Other pages of the same area that also mark this link active (rule L18). */
    activeOn?: NonNullable<InertiaLinkProps['href']>[];
    /** A count shown beside the link; hidden when zero or missing. */
    badge?: number;
};

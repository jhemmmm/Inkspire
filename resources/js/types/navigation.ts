import type { InertiaLinkProps } from '@inertiajs/vue3';
import type { LucideIcon } from '@lucide/vue';

export type BreadcrumbItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
};

export type NavItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
    icon?: LucideIcon;
    isActive?: boolean;
    // Restricts visibility to the listed user roles. Omit for an item every
    // viewer of this nav array may see -- this app's 7 roles each already
    // get their own dedicated portal/nav file; the one exception is
    // Owner/Admin, which share a single portal (UserRole::portalRoute()),
    // so a per-item allowlist is the only way to keep one role-only item
    // (e.g. Reports, D-05) out of the other's sidebar without splitting
    // that shared portal apart.
    roles?: string[];
};

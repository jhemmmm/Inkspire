import { LayoutGrid } from '@lucide/vue';
import { dashboard } from '@/routes/owner';
import type { NavItem } from '@/types';

export const ownerNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
];

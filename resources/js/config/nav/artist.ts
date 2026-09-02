import { LayoutGrid } from '@lucide/vue';
import { dashboard } from '@/routes/artist';
import type { NavItem } from '@/types';

export const artistNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
];

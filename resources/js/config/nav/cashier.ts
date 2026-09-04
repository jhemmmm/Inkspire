import { LayoutGrid } from '@lucide/vue';
import { dashboard } from '@/routes/cashier';
import type { NavItem } from '@/types';

export const cashierNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
];

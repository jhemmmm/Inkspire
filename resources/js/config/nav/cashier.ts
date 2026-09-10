import { ChartColumn, LayoutGrid } from '@lucide/vue';
import { dashboard } from '@/routes/cashier';
import { index as reportsIndex } from '@/routes/cashier/reports';
import type { NavItem } from '@/types';

export const cashierNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
    {
        title: 'Reports',
        href: reportsIndex(),
        icon: ChartColumn,
    },
];

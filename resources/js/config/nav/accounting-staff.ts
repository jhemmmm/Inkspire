import { LayoutGrid } from '@lucide/vue';
import { dashboard } from '@/routes/accounting-staff';
import type { NavItem } from '@/types';

export const accountingStaffNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
];

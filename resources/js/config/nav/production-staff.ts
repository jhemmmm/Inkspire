import { ChartColumn, Factory } from '@lucide/vue';
import { dashboard } from '@/routes/production-staff';
import { index as reportsIndex } from '@/routes/production-staff/reports';
import type { NavItem } from '@/types';

export const productionStaffNavItems: NavItem[] = [
    {
        title: 'Production Board',
        href: dashboard(),
        icon: Factory,
    },
    {
        title: 'Reports',
        href: reportsIndex(),
        icon: ChartColumn,
    },
];

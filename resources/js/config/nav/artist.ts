import { BarChart3, LayoutGrid } from '@lucide/vue';
import { dashboard } from '@/routes/artist';
import { index as performanceReportIndex } from '@/routes/artist/performance-report';
import type { NavItem } from '@/types';

export const artistNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
    {
        title: 'Performance Report',
        href: performanceReportIndex(),
        icon: BarChart3,
    },
];

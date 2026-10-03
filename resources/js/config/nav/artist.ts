import { BarChart3, FilePlus, LayoutGrid } from '@lucide/vue';
import { dashboard } from '@/routes/artist';
import { create } from '@/routes/artist/job-orders';
import { index as performanceReportIndex } from '@/routes/artist/performance-report';
import type { NavItem } from '@/types';

export const artistNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
    {
        title: 'New Job Order',
        href: create(),
        icon: FilePlus,
    },
    {
        title: 'Performance Report',
        href: performanceReportIndex(),
        icon: BarChart3,
    },
];

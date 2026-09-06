import { Factory } from '@lucide/vue';
import { dashboard } from '@/routes/production-staff';
import type { NavItem } from '@/types';

export const productionStaffNavItems: NavItem[] = [
    {
        title: 'Production Board',
        href: dashboard(),
        icon: Factory,
    },
];

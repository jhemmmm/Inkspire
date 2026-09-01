import { LayoutGrid, UserPlus } from '@lucide/vue';
import { dashboard, newVisit } from '@/routes/frontline-staff';
import type { NavItem } from '@/types';

export const frontlineStaffNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
    {
        title: 'New Visit',
        href: newVisit(),
        icon: UserPlus,
    },
];

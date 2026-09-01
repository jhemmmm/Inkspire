import { LayoutGrid, ListOrdered, UserPlus } from '@lucide/vue';
import { dashboard, newVisit } from '@/routes/frontline-staff';
import { index as queueEntriesIndex } from '@/routes/frontline-staff/queue-entries';
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
    {
        title: 'Queue',
        href: queueEntriesIndex(),
        icon: ListOrdered,
    },
];

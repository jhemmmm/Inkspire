import { LayoutGrid, Users } from '@lucide/vue';
import { dashboard } from '@/routes/owner';
import { index as usersIndex } from '@/routes/owner/users';
import type { NavItem } from '@/types';

export const ownerNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
    {
        title: 'User Management',
        href: usersIndex(),
        icon: Users,
    },
];

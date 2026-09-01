import { LayoutGrid, ScrollText, Users } from '@lucide/vue';
import { dashboard } from '@/routes/owner';
import { index as auditTrailIndex } from '@/routes/owner/audit-trail';
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
    {
        title: 'Audit Trail',
        href: auditTrailIndex(),
        icon: ScrollText,
    },
];

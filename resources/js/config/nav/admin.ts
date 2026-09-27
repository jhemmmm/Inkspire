import {
    ChartColumn,
    CreditCard,
    FileMinus,
    LayoutGrid,
    ScrollText,
    Settings,
    Unlock,
    Users,
} from '@lucide/vue';
import { dashboard } from '@/routes/owner';
import { index as auditTrailIndex } from '@/routes/owner/audit-trail';
import { index as creditRequestsIndex } from '@/routes/owner/credit-requests';
import { index as designOverridesIndex } from '@/routes/owner/design-overrides';
import { index as reportsIndex } from '@/routes/owner/reports';
import { edit as systemConfigurationEditRoute } from '@/routes/owner/system-configuration';
import { index as usersIndex } from '@/routes/owner/users';
import { index as writeOffRequestsIndex } from '@/routes/owner/write-off-requests';
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
    {
        title: 'System Configuration',
        href: systemConfigurationEditRoute(),
        icon: Settings,
    },
    {
        title: 'Design Overrides',
        href: designOverridesIndex(),
        icon: Unlock,
    },
    {
        title: 'Credit Requests',
        href: creditRequestsIndex(),
        icon: CreditCard,
    },
    {
        title: 'Write-Off Requests',
        href: writeOffRequestsIndex(),
        icon: FileMinus,
    },
    {
        title: 'Reports',
        href: reportsIndex(),
        icon: ChartColumn,
        // D-05: Admin shares this portal/nav array with Owner
        // (UserRole::portalRoute() maps both to owner.dashboard), and the
        // Reports route lives in a route group scoped to role:owner alone
        // -- so this is the one item on this array Admin must not see.
        roles: ['owner'],
    },
];

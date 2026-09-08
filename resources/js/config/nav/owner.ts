import {
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
];

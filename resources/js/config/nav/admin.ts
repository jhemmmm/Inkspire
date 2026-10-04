import {
    ChartColumn,
    ClipboardList,
    Contact,
    CreditCard,
    FileMinus,
    LayoutGrid,
    ScrollText,
    Ruler,
    Settings,
    Tag,
    Unlock,
    Users,
} from '@lucide/vue';
import { dashboard } from '@/routes/admin';
import { index as auditTrailIndex } from '@/routes/admin/audit-trail';
import { index as creditRequestsIndex } from '@/routes/admin/credit-requests';
import { index as customersIndex } from '@/routes/admin/customers';
import { index as designOverridesIndex } from '@/routes/admin/design-overrides';
import { index as jobOrdersIndex } from '@/routes/admin/job-orders';
import { index as productsIndex } from '@/routes/admin/products';
import { index as reportsIndex } from '@/routes/admin/reports';
import { index as specificationsIndex } from '@/routes/admin/specifications';
import { edit as systemConfigurationEditRoute } from '@/routes/admin/system-configuration';
import { index as usersIndex } from '@/routes/admin/users';
import { index as writeOffRequestsIndex } from '@/routes/admin/write-off-requests';
import type { NavItem } from '@/types';

export const adminNavItems: NavItem[] = [
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
        title: 'Customers',
        href: customersIndex(),
        icon: Contact,
    },
    {
        title: 'Job Orders',
        href: jobOrdersIndex(),
        icon: ClipboardList,
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
        title: 'Products & Services',
        href: productsIndex(),
        icon: Tag,
    },
    {
        title: 'Print Specifications',
        href: specificationsIndex(),
        icon: Ruler,
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
    },
];

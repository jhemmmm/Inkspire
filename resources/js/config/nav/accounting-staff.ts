import { HandCoins, LayoutGrid } from '@lucide/vue';
import { dashboard } from '@/routes/accounting-staff';
import { index as accountsReceivableIndex } from '@/routes/accounting-staff/accounts-receivable';
import type { NavItem } from '@/types';

export const accountingStaffNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
    {
        title: 'Accounts Receivable',
        href: accountsReceivableIndex(),
        icon: HandCoins,
    },
];

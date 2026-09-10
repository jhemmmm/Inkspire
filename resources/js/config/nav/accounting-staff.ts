import { HandCoins, LayoutGrid, Wallet } from '@lucide/vue';
import { dashboard } from '@/routes/accounting-staff';
import { index as accountsReceivableIndex } from '@/routes/accounting-staff/accounts-receivable';
import { index as expensesIndex } from '@/routes/accounting-staff/expenses';
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
    {
        title: 'Expenses',
        href: expensesIndex(),
        icon: Wallet,
    },
];

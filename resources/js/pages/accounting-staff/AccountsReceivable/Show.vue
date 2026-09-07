<script setup lang="ts">
import { Head, setLayoutProps } from '@inertiajs/vue3';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { accountingStaffNavItems } from '@/config/nav/accounting-staff';
import { index as accountsReceivableIndex, show } from '@/routes/accounting-staff/accounts-receivable';

interface AccountsReceivableDetail {
    id: number;
    job_order: {
        id: number;
        number: string | null;
        description: string;
        total_amount: number | null;
        queue_entry: { customer: { name: string | null } | null };
    };
    balance: number;
    credit_extended: number;
    aging_bracket: string;
    days_past_due: number | null;
    collection_status: string;
    due_at: string | null;
    write_off_requested_at: string | null;
    last_reminder_sent_at: string | null;
    approved_at: string | null;
}

const props = defineProps<{
    accountsReceivable: AccountsReceivableDetail;
}>();

defineOptions({
    layout: {
        navItems: accountingStaffNavItems,
    },
});

setLayoutProps({
    breadcrumbs: [
        { title: 'Accounts Receivable', href: accountsReceivableIndex() },
        {
            title: props.accountsReceivable.job_order.number ?? '—',
            href: show.url(props.accountsReceivable.id),
        },
    ],
});

const BRACKET_LABELS: Record<string, string> = {
    current: 'Current',
    one_to_fifteen: '1–15 Days',
    sixteen_to_thirty: '16–30 Days',
    thirty_one_to_sixty: '31–60 Days',
    sixty_one_to_ninety: '61–90 Days',
    ninety_plus: '90+ Days',
};

const COLLECTION_STATUS_LABELS: Record<string, string> = {
    pending: 'Pending',
    follow_up: 'Follow-up',
    warning_sent: 'Warning Sent',
    collections: 'Collections',
    paid: 'Paid',
    written_off: 'Written Off',
};

function agingBadgeProps(bracket: string): {
    variant: 'default' | 'secondary' | 'outline' | 'destructive' | undefined;
    class: string;
} {
    switch (bracket) {
        case 'current':
            return { variant: undefined, class: 'text-green-600 dark:text-green-400' };
        case 'one_to_fifteen':
        case 'sixteen_to_thirty':
            return { variant: 'secondary', class: '' };
        case 'thirty_one_to_sixty':
        case 'sixty_one_to_ninety':
            return {
                variant: 'outline',
                class: 'text-amber-600 dark:text-amber-400 border-amber-600/40',
            };
        case 'ninety_plus':
            return { variant: 'destructive', class: '' };
        default:
            return { variant: undefined, class: '' };
    }
}

function collectionStatusBadgeProps(status: string): {
    variant: 'default' | 'secondary' | 'outline' | 'destructive' | undefined;
    class: string;
} {
    switch (status) {
        case 'pending':
            return { variant: 'outline', class: '' };
        case 'follow_up':
        case 'warning_sent':
            return { variant: 'secondary', class: '' };
        case 'collections':
            return { variant: 'default', class: '' };
        case 'paid':
            return { variant: undefined, class: 'text-green-600 dark:text-green-400' };
        case 'written_off':
            return { variant: 'outline', class: 'text-muted-foreground' };
        default:
            return { variant: undefined, class: '' };
    }
}

function money(value: number): string {
    return `₱${Number(value).toLocaleString('en-PH', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })}`;
}

function dateLabel(value: string | null): string {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleDateString('en-PH', {
        month: 'long',
        day: 'numeric',
        year: 'numeric',
    });
}
</script>

<template>
    <Head :title="`${accountsReceivable.job_order.number ?? '—'} — Accounts Receivable`" />

    <div class="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4">
        <div class="flex flex-col gap-1">
            <h1 class="text-[28px] leading-[1.2] font-semibold">
                {{ accountsReceivable.job_order.number ?? '—' }}
            </h1>
            <p class="text-muted-foreground text-sm">
                {{ accountsReceivable.job_order.queue_entry.customer?.name ?? '—' }}
                ·
                {{ accountsReceivable.job_order.description }}
            </p>
            <div class="flex items-center gap-2">
                <Badge
                    :variant="agingBadgeProps(accountsReceivable.aging_bracket).variant"
                    :class="agingBadgeProps(accountsReceivable.aging_bracket).class"
                >
                    {{ BRACKET_LABELS[accountsReceivable.aging_bracket] ?? accountsReceivable.aging_bracket }}
                </Badge>
                <Badge
                    :variant="collectionStatusBadgeProps(accountsReceivable.collection_status).variant"
                    :class="collectionStatusBadgeProps(accountsReceivable.collection_status).class"
                >
                    {{ COLLECTION_STATUS_LABELS[accountsReceivable.collection_status] ?? accountsReceivable.collection_status }}
                </Badge>
            </div>
        </div>

        <Card>
            <CardHeader>
                <CardTitle>Amounts</CardTitle>
            </CardHeader>
            <CardContent class="grid gap-4">
                <div class="flex items-center justify-between">
                    <span>Credit Extended</span>
                    <span class="tabular-nums">{{ money(accountsReceivable.credit_extended) }}</span>
                </div>
                <div class="flex items-center justify-between">
                    <span>Job Order Total</span>
                    <span class="tabular-nums">{{ money(accountsReceivable.job_order.total_amount ?? 0) }}</span>
                </div>
                <div class="flex items-center justify-between">
                    <span>Amount Paid</span>
                    <span class="tabular-nums">
                        {{ money((accountsReceivable.job_order.total_amount ?? 0) - accountsReceivable.balance) }}
                    </span>
                </div>
                <div class="flex items-center justify-between border-t pt-4">
                    <span class="font-semibold">Outstanding Balance</span>
                    <span class="text-[28px] leading-[1.2] font-semibold tabular-nums">
                        {{ money(accountsReceivable.balance) }}
                    </span>
                </div>
                <p class="text-muted-foreground text-sm">
                    Outstanding is the job order total less every completed
                    payment. Payments are recorded at the Cashier counter.
                </p>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle>Account Details</CardTitle>
            </CardHeader>
            <CardContent class="grid grid-cols-2 gap-4">
                <div class="flex flex-col gap-1">
                    <span class="text-sm font-semibold">Approved On</span>
                    <span>{{ dateLabel(accountsReceivable.approved_at) }}</span>
                </div>
                <div class="flex flex-col gap-1">
                    <span class="text-sm font-semibold">Due Date</span>
                    <span>{{ dateLabel(accountsReceivable.due_at) }}</span>
                </div>
                <div class="flex flex-col gap-1">
                    <span class="text-sm font-semibold">Days Past Due</span>
                    <span v-if="accountsReceivable.days_past_due === null" class="text-muted-foreground">
                        Not yet due
                    </span>
                    <span v-else>{{ accountsReceivable.days_past_due }}</span>
                </div>
                <div class="flex flex-col gap-1">
                    <span class="text-sm font-semibold">Aging Bracket</span>
                    <span>
                        {{ BRACKET_LABELS[accountsReceivable.aging_bracket] ?? accountsReceivable.aging_bracket }}
                    </span>
                </div>
                <div class="flex flex-col gap-1">
                    <span class="text-sm font-semibold">Last Reminder Sent</span>
                    <span v-if="accountsReceivable.last_reminder_sent_at">
                        {{ new Date(accountsReceivable.last_reminder_sent_at).toLocaleString() }}
                    </span>
                    <span v-else class="text-muted-foreground">None sent yet</span>
                </div>
            </CardContent>
        </Card>

        <!-- Collection Status panel: added by 07-04 -->
        <!-- Write-off actions: added by 07-05 -->
    </div>
</template>

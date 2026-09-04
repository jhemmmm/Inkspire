<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableEmpty,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { accountingStaffNavItems } from '@/config/nav/accounting-staff';
import { dashboard } from '@/routes/accounting-staff';
import { reconcile } from '@/routes/accounting-staff/job-orders';

interface PendingConfirmationTransaction {
    payment_method: string;
}

interface PendingConfirmationJobOrder {
    id: number;
    description: string;
    total_amount: number | null;
    created_at: string;
    queue_entry: { customer: { name: string } };
    transactions: PendingConfirmationTransaction[];
}

defineProps<{
    jobOrders: PendingConfirmationJobOrder[];
}>();

defineOptions({
    layout: {
        navItems: accountingStaffNavItems,
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: dashboard(),
            },
        ],
    },
});

const reconcilingId = ref<number | null>(null);

/**
 * A plain router.post() — this is a no-body action, not a page-level form
 * submission, matching Cashier Dashboard's identical action.
 */
function checkPaymentStatus(jobOrderId: number): void {
    reconcilingId.value = jobOrderId;

    router.post(
        reconcile.url(jobOrderId),
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                reconcilingId.value = null;
            },
        },
    );
}

function paymentMethodLabel(method: string | undefined): string {
    switch (method) {
        case 'gcash':
            return 'GCash';
        case 'maya':
            return 'Maya';
        default:
            return '—';
    }
}

function money(value: number | null): string {
    return `₱${Number(value ?? 0).toFixed(2)}`;
}
</script>

<template>
    <Head title="Accounting Staff Dashboard" />

    <div
        class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4"
    >
        <h1 class="text-[28px] leading-[1.2] font-semibold">
            Accounting Dashboard
        </h1>

        <div
            class="border-sidebar-border/70 dark:border-sidebar-border overflow-hidden rounded-xl border"
        >
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Job Order</TableHead>
                        <TableHead>Customer</TableHead>
                        <TableHead>Amount</TableHead>
                        <TableHead>Payment Method</TableHead>
                        <TableHead>Created At</TableHead>
                        <TableHead class="text-right">Actions</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableEmpty v-if="jobOrders.length === 0" :colspan="6">
                        <div
                            class="flex flex-col items-center gap-1 text-center"
                        >
                            <p class="font-semibold">
                                No payments awaiting confirmation
                            </p>
                            <p class="text-muted-foreground">
                                GCash and Maya payments will appear here while
                                waiting on PayMongo.
                            </p>
                        </div>
                    </TableEmpty>
                    <TableRow
                        v-for="jobOrder in jobOrders"
                        v-else
                        :key="jobOrder.id"
                    >
                        <TableCell>{{ jobOrder.description }}</TableCell>
                        <TableCell>
                            {{ jobOrder.queue_entry.customer.name }}
                        </TableCell>
                        <TableCell>
                            {{ money(jobOrder.total_amount) }}
                        </TableCell>
                        <TableCell>
                            {{
                                paymentMethodLabel(
                                    jobOrder.transactions[0]?.payment_method,
                                )
                            }}
                        </TableCell>
                        <TableCell>
                            {{ new Date(jobOrder.created_at).toLocaleString() }}
                        </TableCell>
                        <TableCell class="text-right">
                            <Button
                                :disabled="reconcilingId === jobOrder.id"
                                :data-test="`check-payment-status-${jobOrder.id}-button`"
                                @click="checkPaymentStatus(jobOrder.id)"
                            >
                                Check Payment Status
                            </Button>
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>
    </div>
</template>

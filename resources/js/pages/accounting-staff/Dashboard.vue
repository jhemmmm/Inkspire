<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import DataTableCard from '@/components/DataTableCard.vue';
import EmptyState from '@/components/EmptyState.vue';
import PageContainer from '@/components/PageContainer.vue';
import PageHeader from '@/components/PageHeader.vue';
import SectionHeading from '@/components/SectionHeading.vue';
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

    <PageContainer>
        <PageHeader
            title="Accounting Dashboard"
            description="Payments taken at the counter that still need your confirmation."
        />

        <SectionHeading
            title="Awaiting Confirmation"
            description="Reconcile each payment against the bank or gateway record before confirming."
        />

        <DataTableCard>
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
                        <EmptyState
                            title="No payments awaiting confirmation"
                            description="GCash and Maya payments will appear here while waiting on PayMongo."
                        />
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
                        <TableCell class="tabular-nums">
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
        </DataTableCard>
    </PageContainer>
</template>

<script setup lang="ts">
import { Form, Head, Link, router } from '@inertiajs/vue3';
import { MoreHorizontal, Zap } from '@lucide/vue';
import { ref } from 'vue';
import CancellationController from '@/actions/App/Http/Controllers/Cashier/CancellationController';
import PaymentController from '@/actions/App/Http/Controllers/Cashier/PaymentController';
import ReceiptController from '@/actions/App/Http/Controllers/Cashier/ReceiptController';
import {
    AlertDialog,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
    AlertDialogTrigger,
} from '@/components/ui/alert-dialog';
import DataTableCard from '@/components/DataTableCard.vue';
import EmptyState from '@/components/EmptyState.vue';
import PageContainer from '@/components/PageContainer.vue';
import PageHeader from '@/components/PageHeader.vue';
import SectionHeading from '@/components/SectionHeading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Table,
    TableBody,
    TableCell,
    TableEmpty,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { cashierNavItems } from '@/config/nav/cashier';
import { dashboard } from '@/routes/cashier';
import { reconcile } from '@/routes/cashier/job-orders';

interface CashierJobOrder {
    id: number;
    number: string | null;
    description: string;
    status: string;
    payment_status: string;
    total_amount: number | null;
    amount_paid: number | null;
    is_rush: boolean;
    queue_entry: { customer: { name: string } };
    // Present only when an Active On-Credit receivable exists for this job
    // order (WR-05) — cancelling never writes this balance off, so the
    // dialog surfaces it explicitly rather than leaving it as a silent
    // byproduct of two independent code paths.
    accounts_receivable: { balance: number } | null;
}

const props = defineProps<{
    jobOrders: CashierJobOrder[];
    cancellationFeeAmount: number;
}>();

/**
 * Mirrors CancellationController@store's exact design-started set (D-04) so
 * the pre-confirmation dialog body always matches what the server will
 * actually charge.
 */
const DESIGN_STARTED_STATUSES = [
    'in_design',
    'pending_review',
    'design_approved',
    'for_production',
    'printing',
    'quality_check',
    'ready_for_pickup',
];

function cancellationDialogBody(jobOrder: CashierJobOrder): string {
    let body: string;

    if (!DESIGN_STARTED_STATUSES.includes(jobOrder.status)) {
        body = "No cancellation fee applies — design work hasn't started yet.";
    } else {
        const fee = props.cancellationFeeAmount;
        // Coerced defensively (CR-04) — a decimal-cast/raw-SQL-aggregate
        // money value from the backend can arrive as a numeric string
        // depending on the DB driver (confirmed for MySQL's SUM() in
        // production), and String.prototype has no .toFixed(), matching
        // every other money value in this phase's Vue code (Receipt.vue's
        // money(), etc.).
        const downPayment = Number(jobOrder.amount_paid ?? 0);

        if (downPayment <= 0) {
            body = `A cancellation fee of ₱${fee.toFixed(2)} applies since design work has started. Collect this amount from the customer.`;
        } else if (downPayment >= fee) {
            const excess = (downPayment - fee).toFixed(2);

            body = `A cancellation fee of ₱${fee.toFixed(2)} applies. The existing down payment of ₱${downPayment.toFixed(2)} covers it — ₱${excess} is refundable to the customer.`;
        } else {
            const shortfall = (fee - downPayment).toFixed(2);

            body = `A cancellation fee of ₱${fee.toFixed(2)} applies. The existing down payment of ₱${downPayment.toFixed(2)} covers part of it — collect the remaining ₱${shortfall} from the customer.`;
        }
    }

    // WR-05: cancelling never writes off an existing On-Credit balance —
    // the fee-netting logic above only ever looks at completed
    // Transactions, so an Active AccountsReceivable is untouched by this
    // action. Surface it explicitly rather than leaving the customer's
    // outstanding balance as a silent byproduct.
    if (jobOrder.accounts_receivable) {
        const outstanding = Number(
            jobOrder.accounts_receivable.balance,
        ).toFixed(2);

        body += ` This job order also has an outstanding On-Credit balance of ₱${outstanding} that will NOT be written off by cancelling — follow up on collection separately.`;
    }

    return body;
}

/**
 * Mirrors CancellationController@store's terminal-state guard: a job order
 * that is already fully paid or written off can never be cancelled from
 * here, so the Cancel Job Order action must never be offered for either
 * state (CR-03).
 */
function canCancelJobOrder(jobOrder: CashierJobOrder): boolean {
    return (
        jobOrder.payment_status !== 'paid' &&
        jobOrder.payment_status !== 'written_off'
    );
}

defineOptions({
    layout: {
        navItems: cashierNavItems,
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: dashboard(),
            },
        ],
    },
});

function jobOrderStatusLabel(status: string): string {
    switch (status) {
        case 'ready_for_production':
            return 'Ready for Production';
        case 'design_approved':
            return 'Design Approved';
        case 'for_production':
            return 'For Production';
        case 'printing':
            return 'Printing';
        case 'quality_check':
            return 'Quality Check';
        case 'ready_for_pickup':
            return 'Ready for Pickup';
        default:
            return status;
    }
}

const reconcilingId = ref<number | null>(null);

/**
 * A plain router.post() — this is a no-body action fired from a
 * DropdownMenuItem, not a page-level form submission.
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

function paymentStatusLabel(status: string): string {
    switch (status) {
        case 'unpaid':
            return 'Unpaid';
        case 'partially_paid':
            return 'Partially Paid';
        case 'pending_confirmation':
            return 'Pending Confirmation';
        case 'paid':
            return 'Paid';
        case 'credit_pending_approval':
            return 'Credit Pending Approval';
        case 'on_credit':
            return 'On Credit';
        case 'credit_rejected':
            return 'Credit Rejected';
        case 'written_off':
            return 'Written Off';
        default:
            return status;
    }
}
</script>

<template>
    <Head title="Cashier Dashboard" />

    <PageContainer>
        <PageHeader
            title="Cashier Dashboard"
            description="Job orders waiting to be paid for."
        />

        <SectionHeading
            title="Ready for Payment"
            description="Take payment here, then hand the customer their receipt."
        />

        <DataTableCard>
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Job Order</TableHead>
                        <TableHead>Customer</TableHead>
                        <TableHead>Job Order Status</TableHead>
                        <TableHead>Payment Status</TableHead>
                        <TableHead class="text-right">Actions</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableEmpty v-if="jobOrders.length === 0" :colspan="5">
                        <EmptyState
                            title="No job orders ready for payment"
                            description="Job orders will appear here once they're validated or design-approved."
                        />
                    </TableEmpty>
                    <TableRow
                        v-for="jobOrder in jobOrders"
                        v-else
                        :key="jobOrder.id"
                    >
                        <TableCell>
                            <div class="flex flex-col items-start gap-1">
                                <span
                                    class="text-muted-foreground text-xs tabular-nums"
                                    >{{ jobOrder.number ?? '—' }}</span
                                >
                                <span>{{ jobOrder.description }}</span>
                                <Badge
                                    v-if="jobOrder.is_rush"
                                    variant="outline"
                                    class="border-amber-600/40 text-amber-600 dark:text-amber-400"
                                    :data-test="`cashier-rush-${jobOrder.id}-badge`"
                                >
                                    <Zap class="size-3" />
                                    Rush
                                </Badge>
                            </div>
                        </TableCell>
                        <TableCell>
                            {{ jobOrder.queue_entry.customer.name }}
                        </TableCell>
                        <TableCell>
                            <Badge
                                v-if="
                                    jobOrder.status === 'ready_for_production'
                                "
                                class="text-green-600 dark:text-green-400"
                            >
                                {{ jobOrderStatusLabel(jobOrder.status) }}
                            </Badge>
                            <Badge
                                v-else-if="
                                    jobOrder.status === 'design_approved'
                                "
                                variant="default"
                            >
                                {{ jobOrderStatusLabel(jobOrder.status) }}
                            </Badge>
                            <Badge v-else variant="secondary">
                                {{ jobOrderStatusLabel(jobOrder.status) }}
                            </Badge>
                        </TableCell>
                        <TableCell>
                            <Badge
                                v-if="jobOrder.payment_status === 'unpaid'"
                                variant="outline"
                            >
                                {{
                                    paymentStatusLabel(jobOrder.payment_status)
                                }}
                            </Badge>
                            <Badge
                                v-else-if="
                                    jobOrder.payment_status === 'partially_paid'
                                "
                                variant="default"
                            >
                                {{
                                    paymentStatusLabel(jobOrder.payment_status)
                                }}
                            </Badge>
                            <Badge
                                v-else-if="
                                    jobOrder.payment_status ===
                                    'pending_confirmation'
                                "
                                variant="secondary"
                            >
                                {{
                                    paymentStatusLabel(jobOrder.payment_status)
                                }}
                            </Badge>
                            <Badge
                                v-else-if="jobOrder.payment_status === 'paid'"
                                class="text-green-600 dark:text-green-400"
                            >
                                {{
                                    paymentStatusLabel(jobOrder.payment_status)
                                }}
                            </Badge>
                            <Badge
                                v-else-if="
                                    jobOrder.payment_status ===
                                    'credit_pending_approval'
                                "
                                variant="default"
                            >
                                {{
                                    paymentStatusLabel(jobOrder.payment_status)
                                }}
                            </Badge>
                            <Badge
                                v-else-if="
                                    jobOrder.payment_status === 'on_credit'
                                "
                                class="text-green-600 dark:text-green-400"
                            >
                                {{
                                    paymentStatusLabel(jobOrder.payment_status)
                                }}
                            </Badge>
                            <Badge
                                v-else-if="
                                    jobOrder.payment_status ===
                                    'credit_rejected'
                                "
                                variant="destructive"
                            >
                                {{
                                    paymentStatusLabel(jobOrder.payment_status)
                                }}
                            </Badge>
                            <Badge
                                v-else-if="
                                    jobOrder.payment_status === 'written_off'
                                "
                                variant="outline"
                                class="text-muted-foreground"
                            >
                                {{
                                    paymentStatusLabel(jobOrder.payment_status)
                                }}
                            </Badge>
                        </TableCell>
                        <TableCell class="text-right">
                            <DropdownMenu>
                                <DropdownMenuTrigger as-child>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        :data-test="`job-order-actions-${jobOrder.id}-trigger`"
                                    >
                                        <MoreHorizontal class="size-4" />
                                        <span class="sr-only">Actions</span>
                                    </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="end">
                                    <DropdownMenuItem
                                        v-if="
                                            jobOrder.payment_status ===
                                                'unpaid' ||
                                            jobOrder.payment_status ===
                                                'partially_paid'
                                        "
                                        as-child
                                    >
                                        <Link
                                            :href="
                                                PaymentController.edit(
                                                    jobOrder.id,
                                                ).url
                                            "
                                            :data-test="`process-payment-${jobOrder.id}-link`"
                                        >
                                            Process Payment
                                        </Link>
                                    </DropdownMenuItem>
                                    <DropdownMenuItem
                                        v-else-if="
                                            jobOrder.payment_status ===
                                            'pending_confirmation'
                                        "
                                        :disabled="
                                            reconcilingId === jobOrder.id
                                        "
                                        :data-test="`check-payment-status-${jobOrder.id}-button`"
                                        @select="
                                            checkPaymentStatus(jobOrder.id)
                                        "
                                    >
                                        Check Payment Status
                                    </DropdownMenuItem>
                                    <DropdownMenuItem
                                        v-else-if="
                                            jobOrder.payment_status === 'paid'
                                        "
                                        as-child
                                    >
                                        <Link
                                            :href="
                                                ReceiptController.show(
                                                    jobOrder.id,
                                                ).url
                                            "
                                            :data-test="`view-receipt-${jobOrder.id}-link`"
                                        >
                                            View Receipt
                                        </Link>
                                    </DropdownMenuItem>
                                    <DropdownMenuItem
                                        v-else-if="
                                            jobOrder.payment_status ===
                                                'on_credit' ||
                                            jobOrder.payment_status ===
                                                'credit_rejected'
                                        "
                                        as-child
                                    >
                                        <Link
                                            :href="
                                                PaymentController.edit(
                                                    jobOrder.id,
                                                ).url
                                            "
                                            :data-test="`process-payment-${jobOrder.id}-link`"
                                        >
                                            {{
                                                jobOrder.payment_status ===
                                                'on_credit'
                                                    ? 'Collect Balance'
                                                    : 'Process Payment'
                                            }}
                                        </Link>
                                    </DropdownMenuItem>
                                    <DropdownMenuItem
                                        v-else-if="
                                            jobOrder.payment_status ===
                                            'credit_pending_approval'
                                        "
                                        disabled
                                        :data-test="`credit-pending-approval-${jobOrder.id}-item`"
                                    >
                                        Awaiting Owner Approval
                                    </DropdownMenuItem>
                                    <AlertDialog
                                        v-if="canCancelJobOrder(jobOrder)"
                                    >
                                        <AlertDialogTrigger as-child>
                                            <DropdownMenuItem
                                                variant="destructive"
                                                :data-test="`cancel-job-order-${jobOrder.id}-item`"
                                                @select.prevent
                                            >
                                                Cancel Job Order
                                            </DropdownMenuItem>
                                        </AlertDialogTrigger>
                                        <AlertDialogContent>
                                            <AlertDialogHeader>
                                                <AlertDialogTitle>
                                                    Cancel this job order?
                                                </AlertDialogTitle>
                                                <AlertDialogDescription>
                                                    {{
                                                        cancellationDialogBody(
                                                            jobOrder,
                                                        )
                                                    }}
                                                </AlertDialogDescription>
                                            </AlertDialogHeader>
                                            <AlertDialogFooter>
                                                <AlertDialogCancel>
                                                    Cancel
                                                </AlertDialogCancel>
                                                <Form
                                                    v-bind="
                                                        CancellationController.store.form(
                                                            jobOrder.id,
                                                        )
                                                    "
                                                    :options="{
                                                        preserveScroll: true,
                                                    }"
                                                    v-slot="{ processing }"
                                                >
                                                    <Button
                                                        type="submit"
                                                        variant="destructive"
                                                        :disabled="processing"
                                                        :data-test="`confirm-cancel-${jobOrder.id}-button`"
                                                    >
                                                        Confirm Cancellation
                                                    </Button>
                                                </Form>
                                            </AlertDialogFooter>
                                        </AlertDialogContent>
                                    </AlertDialog>
                                </DropdownMenuContent>
                            </DropdownMenu>
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </DataTableCard>
    </PageContainer>
</template>

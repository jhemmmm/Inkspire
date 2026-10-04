<script setup lang="ts">
import { Form, Head, Link, router } from '@inertiajs/vue3';
import { MoreHorizontal, Zap } from '@lucide/vue';
import { computed, ref } from 'vue';
import CancellationController from '@/actions/App/Http/Controllers/Cashier/CancellationController';
import PaymentController from '@/actions/App/Http/Controllers/Cashier/PaymentController';
import ReceiptController from '@/actions/App/Http/Controllers/Cashier/ReceiptController';
import ReconciliationController from '@/actions/App/Http/Controllers/Cashier/ReconciliationController';
import {
    AlertDialog,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
    AlertDialogTrigger,
} from '@/components/alert-dialog';
import JobOrderTotal from '@/components/JobOrderTotal.vue';
import DataTableCard from '@/components/DataTableCard.vue';
import EmptyState from '@/components/EmptyState.vue';
import PageContainer from '@/components/PageContainer.vue';
import PageHeader from '@/components/PageHeader.vue';
import SectionHeading from '@/components/SectionHeading.vue';
import TableFilterBar from '@/components/TableFilterBar.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableEmpty,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useLivePoll } from '@/composables/useLivePoll';
import { useTableFilter } from '@/composables/useTableFilter';
import { cashierNavItems } from '@/config/nav/cashier';
import {
    balanceLabel,
    jobOrderStatusBadge,
    jobOrderStatusLabel,
    money,
    paymentStatusBadge,
    paymentStatusLabel,
} from '@/lib/jobOrders';
import { dashboard } from '@/routes/cashier';
import { reconcile } from '@/routes/cashier/job-orders';

interface CashierJobOrder {
    id: number;
    number: string | null;
    description: string;
    status: string;
    // The status to show: 'released' once the customer has the order.
    display_status: string;
    // Server-decided, from JobOrder::cancellationBlocker().
    can_cancel: boolean;
    payment_status: string;
    total_amount: number | null;
    display_total: number | null;
    amount_paid: number | null;
    is_rush: boolean;
    queue_entry: { queue_prefix: string; customer: { name: string } };
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

// A job order appears here the moment an Artist or the customer approves its
// design, and leaves it once it is paid, without the Cashier reloading.
useLivePoll(['jobOrders']);

const ALL = 'all';

const paymentStatusOptions = computed(() => {
    const statuses = Array.from(
        new Set(props.jobOrders.map((jobOrder) => jobOrder.payment_status)),
    );

    return [
        { value: ALL, label: 'All payment statuses' },
        ...statuses.map((status) => ({
            value: status,
            label: paymentStatusLabel(status),
        })),
    ];
});

const paymentStatusFilter = ref(ALL);

const { searchTerm, filtered: filteredJobOrders } = useTableFilter(
    () => props.jobOrders,
    (jobOrder) => [
        jobOrder.number,
        jobOrder.queue_entry.customer.name,
        jobOrder.description,
    ],
    {
        filters: [
            (jobOrder) =>
                paymentStatusFilter.value === ALL ||
                jobOrder.payment_status === paymentStatusFilter.value,
        ],
    },
);

const filtersActive = computed(
    () => searchTerm.value.trim() !== '' || paymentStatusFilter.value !== ALL,
);

function clearFilters(): void {
    searchTerm.value = '';
    paymentStatusFilter.value = ALL;
}

/**
 * Rush and regular as two lists. The cashier's decision on a rush job is
 * different — the rush fee toggle is theirs to set — so the two are worth
 * separating rather than distinguishing by a badge partway down one table.
 *
 * `baseCount` is the unfiltered rush/regular split (D5) — it drives which
 * "nothing at all" empty state a section shows, kept separate from `rows`
 * (the search/payment-status-filtered set) so an empty filtered section on a
 * non-empty rush/regular split renders "No matches," not the all-time-empty
 * copy.
 */
const paymentGroups = computed(() => [
    {
        key: 'rush',
        title: 'Ready for Payment — Rush Print',
        description:
            'Priority jobs. Decide the rush fee here, then hand over the receipt.',
        baseCount: props.jobOrders.filter((jobOrder) => jobOrder.is_rush)
            .length,
        rows: filteredJobOrders.value.filter((jobOrder) => jobOrder.is_rush),
        emptyTitle: 'No rush jobs waiting on payment',
        emptyDescription:
            'Rush jobs appear here once they are validated or design-approved.',
    },
    {
        key: 'regular',
        title: 'Ready for Payment — Regular',
        description: 'Take payment here, then hand the customer their receipt.',
        baseCount: props.jobOrders.filter((jobOrder) => !jobOrder.is_rush)
            .length,
        rows: filteredJobOrders.value.filter((jobOrder) => !jobOrder.is_rush),
        emptyTitle: 'No job orders ready for payment',
        emptyDescription:
            "Job orders will appear here once they're validated or design-approved.",
    },
]);

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
    'ready_for_pickup',
];

/**
 * Whether this job order has a receipt worth opening.
 *
 * Any completed money against the order counts, not just a settled one: a
 * customer who leaves a down payment is handed a receipt for what they paid,
 * showing the balance they still owe.
 */
function hasReceipt(jobOrder: CashierJobOrder): boolean {
    return Number(jobOrder.amount_paid ?? 0) > 0;
}

function cancellationDialogBody(jobOrder: CashierJobOrder): string {
    let body: string;

    if (!DESIGN_STARTED_STATUSES.includes(jobOrder.status)) {
        body = "No cancellation fee applies — design work hasn't started yet.";
    } else {
        const fee = props.cancellationFeeAmount;
        // Coerced defensively (CR-04) — a decimal-cast/raw-SQL-aggregate
        // money value from the backend can arrive as a numeric string
        // depending on the DB driver (confirmed for MySQL's SUM() in
        // production), and the subtractions below need a number.
        const downPayment = Number(jobOrder.amount_paid ?? 0);

        if (downPayment <= 0) {
            body = `A cancellation fee of ${money(fee)} applies since design work has started. Collect this amount from the customer.`;
        } else if (downPayment >= fee) {
            body = `A cancellation fee of ${money(fee)} applies. The existing down payment of ${money(downPayment)} covers it — ${money(downPayment - fee)} is refundable to the customer.`;
        } else {
            body = `A cancellation fee of ${money(fee)} applies. The existing down payment of ${money(downPayment)} covers part of it — collect the remaining ${money(fee - downPayment)} from the customer.`;
        }
    }

    // WR-05: cancelling voids the print-job debt. CancellationController
    // closes the Active receivable so it stops ageing and stops generating
    // reminders; only the cancellation fee above stands. Say so, so the
    // Cashier doesn't chase a balance the system has already closed.
    if (jobOrder.accounts_receivable) {
        body += ` Its On-Credit balance of ${money(Number(jobOrder.accounts_receivable.balance))} is closed by cancelling — the customer no longer owes it.`;
    }

    return body;
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

/**
 * Withdraw a checkout the customer opened online and did not finish, so the
 * payment can be taken at the counter instead.
 */
function cancelOnlinePayment(jobOrderId: number): void {
    reconcilingId.value = jobOrderId;

    router.delete(ReconciliationController.destroy.url(jobOrderId), {
        preserveScroll: true,
        onFinish: () => {
            reconcilingId.value = null;
        },
    });
}
</script>

<template>
    <Head title="Cashier Dashboard" />

    <PageContainer>
        <PageHeader
            title="Cashier Dashboard"
            description="Job orders waiting to be paid for."
        />

        <TableFilterBar
            v-if="jobOrders.length > 0"
            v-model:search="searchTerm"
            search-label="Search job orders"
            search-placeholder="Job order, customer or description"
            :shown="filteredJobOrders.length"
            :total="jobOrders.length"
            :active="filtersActive"
            @clear="clearFilters"
        >
            <div class="flex min-w-0 flex-col gap-2 @lg:w-56">
                <Label for="cashier-payment-status-filter"
                    >Payment status</Label
                >
                <Select v-model="paymentStatusFilter">
                    <SelectTrigger
                        id="cashier-payment-status-filter"
                        class="w-full"
                    >
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="option in paymentStatusOptions"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>
        </TableFilterBar>

        <template v-for="group in paymentGroups" :key="group.key">
            <SectionHeading
                :title="group.title"
                :description="group.description"
            />

            <DataTableCard>
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Job Order</TableHead>
                            <TableHead>Customer</TableHead>
                            <TableHead>Job Order Status</TableHead>
                            <TableHead>Payment Status</TableHead>
                            <TableHead class="text-right">Total</TableHead>
                            <TableHead class="text-right">Balance</TableHead>
                            <TableHead class="text-right">Actions</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableEmpty v-if="group.baseCount === 0" :colspan="7">
                            <EmptyState
                                :title="group.emptyTitle"
                                :description="group.emptyDescription"
                            />
                        </TableEmpty>
                        <TableEmpty
                            v-else-if="group.rows.length === 0"
                            :colspan="7"
                        >
                            <EmptyState
                                title="No matches"
                                description="No job orders in this section match that search or payment status filter."
                            >
                                <template #actions>
                                    <Button
                                        type="button"
                                        variant="secondary"
                                        :data-test="`clear-cashier-${group.key}-filters-button`"
                                        @click="clearFilters"
                                    >
                                        Clear filters
                                    </Button>
                                </template>
                            </EmptyState>
                        </TableEmpty>
                        <TableRow
                            v-for="jobOrder in group.rows"
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
                                        class="border-brand/40 text-brand"
                                        :data-test="`cashier-rush-${jobOrder.id}-badge`"
                                    >
                                        <Zap class="size-3" />
                                        Rush
                                    </Badge>
                                </div>
                            </TableCell>
                            <TableCell>
                                <div class="flex flex-wrap items-center gap-2">
                                    {{ jobOrder.queue_entry.customer.name }}
                                    <Badge
                                        v-if="
                                            jobOrder.queue_entry
                                                .queue_prefix === 'O'
                                        "
                                        variant="outline"
                                        :data-test="`online-badge-${jobOrder.id}`"
                                    >
                                        Online
                                    </Badge>
                                </div>
                            </TableCell>
                            <TableCell>
                                <StatusBadge
                                    :tone="
                                        jobOrderStatusBadge(
                                            jobOrder.display_status,
                                        )
                                    "
                                >
                                    {{
                                        jobOrderStatusLabel(
                                            jobOrder.display_status,
                                        )
                                    }}
                                </StatusBadge>
                            </TableCell>
                            <TableCell>
                                <StatusBadge
                                    :tone="
                                        paymentStatusBadge(
                                            jobOrder.payment_status,
                                        )
                                    "
                                >
                                    {{
                                        paymentStatusLabel(
                                            jobOrder.payment_status,
                                        )
                                    }}
                                </StatusBadge>
                            </TableCell>
                            <TableCell class="text-right tabular-nums">
                                <JobOrderTotal :job-order="jobOrder" />
                            </TableCell>
                            <TableCell class="text-right tabular-nums">
                                {{ balanceLabel(jobOrder) }}
                            </TableCell>
                            <TableCell class="text-right">
                                <DropdownMenu>
                                    <DropdownMenuTrigger as-child>
                                        <Button
                                            variant="outline"
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
                                            v-if="
                                                jobOrder.payment_status ===
                                                'pending_confirmation'
                                            "
                                            :disabled="
                                                reconcilingId === jobOrder.id
                                            "
                                            :data-test="`cancel-online-payment-${jobOrder.id}-button`"
                                            @select="
                                                cancelOnlinePayment(jobOrder.id)
                                            "
                                        >
                                            Cancel Online Payment
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
                                            Awaiting Admin Approval
                                        </DropdownMenuItem>
                                        <DropdownMenuItem
                                            v-if="hasReceipt(jobOrder)"
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
                                        <AlertDialog v-if="jobOrder.can_cancel">
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
                                                            :disabled="
                                                                processing
                                                            "
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
        </template>
    </PageContainer>
</template>

<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { MoreHorizontal } from '@lucide/vue';
import { ref } from 'vue';
import PaymentController from '@/actions/App/Http/Controllers/Cashier/PaymentController';
import ReceiptController from '@/actions/App/Http/Controllers/Cashier/ReceiptController';
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
    description: string;
    status: string;
    payment_status: string;
    total_amount: number | null;
    queue_entry: { customer: { name: string } };
}

defineProps<{
    jobOrders: CashierJobOrder[];
}>();

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
        default:
            return status;
    }
}
</script>

<template>
    <Head title="Cashier Dashboard" />

    <div
        class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4"
    >
        <h1 class="text-[28px] leading-[1.2] font-semibold">
            Cashier Dashboard
        </h1>

        <div
            class="border-sidebar-border/70 dark:border-sidebar-border overflow-hidden rounded-xl border"
        >
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
                        <div
                            class="flex flex-col items-center gap-1 text-center"
                        >
                            <p class="font-semibold">
                                No job orders ready for payment
                            </p>
                            <p class="text-muted-foreground">
                                Job orders will appear here once they're
                                validated or design-approved.
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
                                </DropdownMenuContent>
                            </DropdownMenu>
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>
    </div>
</template>

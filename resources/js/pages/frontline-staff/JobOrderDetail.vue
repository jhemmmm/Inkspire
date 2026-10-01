<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Ban,
    CircleAlert,
    FileCheck2,
    PackageCheck,
    Palette,
    Receipt,
    User,
    Zap,
} from '@lucide/vue';
import { computed } from 'vue';
import DataTableCard from '@/components/DataTableCard.vue';
import EmptyState from '@/components/EmptyState.vue';
import PageContainer from '@/components/PageContainer.vue';
import PageHeader from '@/components/PageHeader.vue';
import SectionHeading from '@/components/SectionHeading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableEmpty,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { frontlineStaffNavItems } from '@/config/nav/frontline-staff';
import {
    jobOrderStatusLabel,
    jobOrderTypeLabel,
    money,
    paymentMethodLabel,
    paymentStatusLabel,
} from '@/lib/jobOrders';
import { dashboard } from '@/routes/frontline-staff';

interface JobOrderDetail {
    id: number;
    number: string | null;
    description: string;
    type: string;
    status: string;
    is_rush: boolean;
    print_size: string | null;
    width_ft: string | null;
    height_ft: string | null;
    quantity: number | null;
    deadline: string | null;
    due_at: string | null;
    client_notes: string | null;
    validation_failure_reason: string | null;
    has_file: boolean;
    has_design_file: boolean;
    payment_status: string;
    base_price_snapshot: number | null;
    rush_fee_applied: boolean;
    rush_fee_amount: number | null;
    discount_amount: number | null;
    total_amount: number | null;
    outstanding_balance: number;
    created_at: string | null;
    accepted_at: string | null;
    released_at: string | null;
    cancelled_at: string | null;
    tracking_token: string | null;
    queue_entry: {
        label: string;
        queue_date: string | null;
        customer: {
            name: string;
            contact_number: string | null;
            email: string | null;
            organization: string | null;
        } | null;
    } | null;
    assigned_artist: { name: string; artist_label: string | null } | null;
    pricing_entry: {
        name: string;
        base_price: number;
        unit: string | null;
    } | null;
    transactions: {
        id: number;
        amount: number;
        method: string | null;
        status: string;
        created_at: string | null;
    }[];
    production_logs: {
        id: number;
        from_status: string | null;
        to_status: string;
        created_at: string | null;
    }[];
}

const props = defineProps<{ jobOrder: JobOrderDetail }>();

defineOptions({
    layout: {
        navItems: frontlineStaffNavItems,
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: 'Job Order' },
        ],
    },
});

function dateTime(iso: string | null): string {
    if (iso === null) {
        return '—';
    }

    return new Date(iso).toLocaleString('en-PH', {
        year: 'numeric',
        month: 'short',
        day: '2-digit',
        hour: 'numeric',
        minute: '2-digit',
    });
}

function date(value: string | null): string {
    if (value === null) {
        return '—';
    }

    return new Date(value).toLocaleDateString('en-PH', {
        year: 'numeric',
        month: 'short',
        day: '2-digit',
    });
}

/**
 * The one line that answers "so what do I tell the customer?". Terminal
 * states win over the stage, because a released or cancelled job order's
 * last production status is no longer the useful answer.
 */
const headline = computed((): string => {
    if (props.jobOrder.cancelled_at !== null) {
        return 'Cancelled';
    }

    if (props.jobOrder.released_at !== null) {
        return 'Released to the customer';
    }

    return jobOrderStatusLabel(props.jobOrder.status);
});

const customer = computed(() => props.jobOrder.queue_entry?.customer ?? null);

const completedPaid = computed((): number =>
    props.jobOrder.transactions
        .filter((transaction) => transaction.status === 'completed')
        .reduce((total, transaction) => total + Number(transaction.amount), 0),
);
</script>

<template>
    <Head :title="`Job Order — ${jobOrder.number ?? jobOrder.id}`" />

    <PageContainer>
        <Button
            as-child
            variant="ghost"
            size="sm"
            class="w-fit"
            data-test="back-to-dashboard-link"
        >
            <Link :href="dashboard()">
                <ArrowLeft class="size-4" />
                Back to dashboard
            </Link>
        </Button>

        <PageHeader
            :title="jobOrder.number ?? `Job Order #${jobOrder.id}`"
            :description="jobOrder.description"
        >
            <template #actions>
                <Badge
                    v-if="jobOrder.is_rush"
                    variant="outline"
                    class="border-brand/40 text-brand"
                    data-test="detail-rush-badge"
                >
                    <Zap class="size-3" />
                    Rush Print
                </Badge>
                <Badge variant="outline">
                    {{ jobOrderTypeLabel(jobOrder.type) }}
                </Badge>
                <Badge variant="secondary" data-test="detail-stage-badge">
                    {{ headline }}
                </Badge>
            </template>
        </PageHeader>

        <div
            v-if="jobOrder.cancelled_at !== null"
            class="border-destructive/40 bg-destructive/5 text-destructive flex items-start gap-3 rounded-xl border p-4"
        >
            <Ban class="mt-0.5 size-5 shrink-0" />
            <div class="flex flex-col gap-0.5">
                <p class="font-semibold">This job order was cancelled</p>
                <p class="text-sm">
                    Cancelled {{ dateTime(jobOrder.cancelled_at) }}. Nothing
                    further happens to it.
                </p>
            </div>
        </div>

        <!--
            The file check's verdict, in the customer's words. This is the
            answer to "why is my print-ready file sitting with an artist?",
            so it sits above the fold rather than inside the order card.
        -->
        <div
            v-if="jobOrder.validation_failure_reason"
            class="border-brand/40 bg-brand/5 flex items-start gap-3 rounded-xl border p-4"
            data-test="detail-validation-notice"
        >
            <CircleAlert class="text-brand mt-0.5 size-5 shrink-0" />
            <div class="flex flex-col gap-0.5">
                <p class="font-semibold">
                    The uploaded file wasn't print-ready
                </p>
                <p class="text-muted-foreground text-sm">
                    {{ jobOrder.validation_failure_reason }}
                </p>
            </div>
        </div>

        <div class="grid gap-6 @3xl:grid-cols-2">
            <Card>
                <CardHeader :icon="User">
                    <CardTitle>Customer</CardTitle>
                </CardHeader>
                <CardContent class="grid gap-3 text-sm">
                    <div class="flex justify-between gap-4">
                        <span class="text-muted-foreground">Name</span>
                        <span class="text-right font-medium">
                            {{ customer?.name ?? '—' }}
                        </span>
                    </div>
                    <div
                        v-if="customer?.organization"
                        class="flex justify-between gap-4"
                    >
                        <span class="text-muted-foreground">Organization</span>
                        <span class="text-right">
                            {{ customer.organization }}
                        </span>
                    </div>
                    <div class="flex justify-between gap-4">
                        <span class="text-muted-foreground">Contact</span>
                        <span class="text-right tabular-nums">
                            {{ customer?.contact_number ?? '—' }}
                        </span>
                    </div>
                    <div class="flex justify-between gap-4">
                        <span class="text-muted-foreground">Email</span>
                        <span class="text-right break-all">
                            {{ customer?.email ?? '—' }}
                        </span>
                    </div>
                    <div class="flex justify-between gap-4">
                        <span class="text-muted-foreground">Queue number</span>
                        <span class="text-right font-medium tabular-nums">
                            {{ jobOrder.queue_entry?.label ?? '—' }}
                        </span>
                    </div>
                    <div class="flex justify-between gap-4">
                        <span class="text-muted-foreground">Queued on</span>
                        <span class="text-right tabular-nums">
                            {{ date(jobOrder.queue_entry?.queue_date ?? null) }}
                        </span>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader :icon="FileCheck2">
                    <CardTitle>Order</CardTitle>
                </CardHeader>
                <CardContent class="grid gap-3 text-sm">
                    <div class="flex justify-between gap-4">
                        <span class="text-muted-foreground">
                            Product / service
                        </span>
                        <span class="text-right font-medium">
                            {{ jobOrder.pricing_entry?.name ?? '—' }}
                        </span>
                    </div>
                    <div class="flex justify-between gap-4">
                        <span class="text-muted-foreground">Print size</span>
                        <span class="text-right">
                            {{ jobOrder.print_size ?? '—' }}
                        </span>
                    </div>
                    <div class="flex justify-between gap-4">
                        <span class="text-muted-foreground">Size</span>
                        <span class="text-right tabular-nums">
                            {{
                                jobOrder.width_ft && jobOrder.height_ft
                                    ? `${jobOrder.width_ft} ft × ${jobOrder.height_ft} ft`
                                    : '—'
                            }}
                        </span>
                    </div>
                    <div class="flex justify-between gap-4">
                        <span class="text-muted-foreground">Quantity</span>
                        <span class="text-right tabular-nums">
                            {{ jobOrder.quantity ?? '—' }}
                        </span>
                    </div>
                    <div class="flex justify-between gap-4">
                        <span class="text-muted-foreground">Deadline</span>
                        <span class="text-right tabular-nums">
                            {{ date(jobOrder.deadline) }}
                        </span>
                    </div>
                    <div class="flex justify-between gap-4">
                        <span class="text-muted-foreground">Created</span>
                        <span class="text-right tabular-nums">
                            {{ dateTime(jobOrder.created_at) }}
                        </span>
                    </div>
                    <div
                        v-if="jobOrder.client_notes"
                        class="border-border flex flex-col gap-1 border-t pt-3"
                    >
                        <span class="text-muted-foreground">Notes</span>
                        <p class="whitespace-pre-line">
                            {{ jobOrder.client_notes }}
                        </p>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader :icon="Palette">
                    <CardTitle>Handling</CardTitle>
                </CardHeader>
                <CardContent class="grid gap-3 text-sm">
                    <div class="flex justify-between gap-4">
                        <span class="text-muted-foreground">Stage</span>
                        <span class="text-right font-medium">
                            {{ jobOrderStatusLabel(jobOrder.status) }}
                        </span>
                    </div>
                    <div class="flex justify-between gap-4">
                        <span class="text-muted-foreground">Artist</span>
                        <span class="text-right">
                            {{ jobOrder.assigned_artist?.name ?? 'Unassigned' }}
                        </span>
                    </div>
                    <div class="flex justify-between gap-4">
                        <span class="text-muted-foreground">
                            Customer's file
                        </span>
                        <span class="text-right">
                            {{ jobOrder.has_file ? 'Uploaded' : 'None' }}
                        </span>
                    </div>
                    <div class="flex justify-between gap-4">
                        <span class="text-muted-foreground">Design file</span>
                        <span class="text-right">
                            {{ jobOrder.has_design_file ? 'Uploaded' : 'None' }}
                        </span>
                    </div>
                    <div class="flex justify-between gap-4">
                        <span class="text-muted-foreground">Accepted</span>
                        <span class="text-right tabular-nums">
                            {{ dateTime(jobOrder.accepted_at) }}
                        </span>
                    </div>
                    <div class="flex justify-between gap-4">
                        <span class="text-muted-foreground">Released</span>
                        <span class="text-right tabular-nums">
                            {{ dateTime(jobOrder.released_at) }}
                        </span>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader :icon="Receipt">
                    <CardTitle>Payment</CardTitle>
                </CardHeader>
                <CardContent class="grid gap-3 text-sm">
                    <div class="flex justify-between gap-4">
                        <span class="text-muted-foreground">Status</span>
                        <Badge variant="outline">
                            {{ paymentStatusLabel(jobOrder.payment_status) }}
                        </Badge>
                    </div>
                    <div class="flex justify-between gap-4">
                        <span class="text-muted-foreground">Base price</span>
                        <span class="text-right tabular-nums">
                            {{ money(jobOrder.base_price_snapshot) }}
                        </span>
                    </div>
                    <div
                        v-if="jobOrder.rush_fee_applied"
                        class="flex justify-between gap-4"
                    >
                        <span class="text-muted-foreground">Rush fee</span>
                        <span class="text-right tabular-nums">
                            {{ money(jobOrder.rush_fee_amount) }}
                        </span>
                    </div>
                    <div
                        v-if="Number(jobOrder.discount_amount ?? 0) > 0"
                        class="flex justify-between gap-4"
                    >
                        <span class="text-muted-foreground">Discount</span>
                        <span class="text-right tabular-nums">
                            −{{ money(jobOrder.discount_amount) }}
                        </span>
                    </div>
                    <div
                        class="border-border flex justify-between gap-4 border-t pt-3 font-semibold"
                    >
                        <span>Total</span>
                        <span class="tabular-nums">
                            {{ money(jobOrder.total_amount) }}
                        </span>
                    </div>
                    <div class="flex justify-between gap-4">
                        <span class="text-muted-foreground">Paid</span>
                        <span class="text-right tabular-nums">
                            {{ money(completedPaid) }}
                        </span>
                    </div>
                    <div class="flex justify-between gap-4 font-semibold">
                        <span>Balance</span>
                        <span
                            class="tabular-nums"
                            data-test="detail-outstanding-balance"
                        >
                            {{ money(jobOrder.outstanding_balance) }}
                        </span>
                    </div>
                </CardContent>
            </Card>
        </div>

        <SectionHeading
            title="History"
            description="Every stage this job order has moved through, oldest first."
        />

        <DataTableCard>
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Stage</TableHead>
                        <TableHead>From</TableHead>
                        <TableHead>When</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableEmpty
                        v-if="jobOrder.production_logs.length === 0"
                        :colspan="3"
                    >
                        <EmptyState
                            title="No stage changes yet"
                            description="Entries appear here as the job order moves through production."
                        />
                    </TableEmpty>
                    <TableRow
                        v-for="log in jobOrder.production_logs"
                        v-else
                        :key="log.id"
                    >
                        <TableCell class="font-medium">
                            {{ jobOrderStatusLabel(log.to_status) }}
                        </TableCell>
                        <TableCell class="text-muted-foreground">
                            {{
                                log.from_status
                                    ? jobOrderStatusLabel(log.from_status)
                                    : '—'
                            }}
                        </TableCell>
                        <TableCell class="text-muted-foreground tabular-nums">
                            {{ dateTime(log.created_at) }}
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </DataTableCard>

        <SectionHeading
            title="Transactions"
            description="Payments recorded against this job order."
        />

        <DataTableCard>
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>When</TableHead>
                        <TableHead>Method</TableHead>
                        <TableHead>Status</TableHead>
                        <TableHead class="text-right">Amount</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableEmpty
                        v-if="jobOrder.transactions.length === 0"
                        :colspan="4"
                    >
                        <EmptyState
                            title="No payment recorded yet"
                            description="The Cashier records payment when the customer settles."
                        />
                    </TableEmpty>
                    <TableRow
                        v-for="transaction in jobOrder.transactions"
                        v-else
                        :key="transaction.id"
                    >
                        <TableCell class="tabular-nums">
                            {{ dateTime(transaction.created_at) }}
                        </TableCell>
                        <TableCell>
                            {{ paymentMethodLabel(transaction.method) }}
                        </TableCell>
                        <TableCell class="text-muted-foreground">
                            {{ transaction.status }}
                        </TableCell>
                        <TableCell class="text-right tabular-nums">
                            {{ money(transaction.amount) }}
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </DataTableCard>

        <div
            v-if="jobOrder.released_at !== null"
            class="text-muted-foreground flex items-center gap-2 text-sm"
        >
            <PackageCheck class="size-4" />
            Released to the customer on {{ dateTime(jobOrder.released_at) }}.
        </div>
    </PageContainer>
</template>

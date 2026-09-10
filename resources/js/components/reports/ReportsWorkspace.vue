<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { FileSpreadsheet, FileText, Info, Zap } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import {
    Table,
    TableBody,
    TableCell,
    TableEmpty,
    TableFooter,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import DateRangeControl from '@/components/reports/DateRangeControl.vue';

interface ReportDefinition {
    title: string;
    subLine: string;
    badge: string;
    columns: string[];
}

interface FinancialSummary {
    job_sales: number;
    cancellation_fees: number;
    revenue_total: number;
    expenses_total: number;
    result: number;
    write_off_total: number;
}

interface ReportFilters {
    from: string;
    to: string;
}

const props = defineProps<{
    reports: Record<string, ReportDefinition>;
    selected: string;
    columns: string[];
    rows: Record<string, unknown>[];
    rowsTotal: number;
    summary: FinancialSummary | null;
    filters: ReportFilters;
    indexUrl: string;
    exportPdfUrl: (key: string) => string;
    exportXlsxUrl: (key: string) => string;
}>();

// One field-key list per report key, in the exact order ReportBuilder's rows()
// emits them (app/Services/Reports/ReportBuilder.php) -- zipped with the
// `columns` prop's display headers, which arrive in the same order.
const REPORT_ROW_FIELDS: Record<string, string[]> = {
    sales: ['date', 'job_order', 'customer', 'type', 'method', 'amount'],
    cancellations: [
        'date',
        'job_order',
        'customer',
        'job_order_total',
        'cancellation_fee',
        'payment_status',
    ],
    'production-status': [
        'job_order',
        'customer',
        'product',
        'stage',
        'urgency',
        'entered_production',
        'due',
    ],
    expenses: [
        'date',
        'category',
        'description',
        'amount',
        'recorded_by',
        'status',
    ],
};

const MONEY_FIELDS = new Set(['amount', 'job_order_total', 'cancellation_fee']);

const rowFields = computed(() => REPORT_ROW_FIELDS[props.selected] ?? []);
const selectedReport = computed(() => props.reports[props.selected]);

function cellClass(field: string): string {
    return MONEY_FIELDS.has(field) ? 'text-right tabular-nums' : '';
}

function displayValue(value: unknown): string {
    return value === null || value === undefined || value === ''
        ? '—'
        : String(value);
}

function moneyField(value: unknown): string {
    return money(Number(value ?? 0));
}

function dateField(value: unknown): string {
    return value ? formatDateOnly(String(value)) : '—';
}

function paymentMethodLabel(method: unknown): string {
    switch (method) {
        case 'cash':
            return 'Cash';
        case 'bank_transfer':
            return 'Bank Transfer';
        case 'gcash':
            return 'GCash';
        case 'maya':
            return 'Maya';
        default:
            return displayValue(method);
    }
}

function transactionTypeLabel(type: unknown): string {
    switch (type) {
        case 'down_payment':
            return 'Down Payment';
        case 'balance_payment':
            return 'Balance Payment';
        case 'full_payment':
            return 'Full Payment';
        case 'cancellation_fee':
            return 'Cancellation Fee';
        default:
            return displayValue(type);
    }
}

type BadgeVariant = 'default' | 'secondary' | 'outline' | 'destructive' | undefined;

function paymentStatusLabel(status: unknown): string {
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
            return displayValue(status);
    }
}

function paymentStatusBadgeProps(status: unknown): {
    variant: BadgeVariant;
    class: string;
} {
    switch (status) {
        case 'unpaid':
            return { variant: 'outline', class: '' };
        case 'partially_paid':
            return { variant: 'default', class: '' };
        case 'pending_confirmation':
            return { variant: 'secondary', class: '' };
        case 'paid':
            return { variant: undefined, class: 'text-green-600 dark:text-green-400' };
        case 'credit_pending_approval':
            return { variant: 'default', class: '' };
        case 'on_credit':
            return { variant: undefined, class: 'text-green-600 dark:text-green-400' };
        case 'credit_rejected':
            return { variant: 'destructive', class: '' };
        case 'written_off':
            return { variant: 'outline', class: 'text-muted-foreground' };
        default:
            return { variant: undefined, class: '' };
    }
}

function jobOrderStageLabel(status: unknown): string {
    switch (status) {
        case 'for_production':
            return 'For Production';
        case 'printing':
            return 'Printing';
        case 'quality_check':
            return 'Quality Check';
        case 'ready_for_pickup':
            return 'Ready for Pickup';
        default:
            return displayValue(status);
    }
}

function jobOrderStageBadgeProps(status: unknown): {
    variant: BadgeVariant;
    class: string;
} {
    switch (status) {
        case 'for_production':
            return { variant: 'outline', class: '' };
        case 'printing':
            return { variant: 'secondary', class: '' };
        case 'quality_check':
            return { variant: 'secondary', class: '' };
        case 'ready_for_pickup':
            return { variant: undefined, class: 'text-green-600 dark:text-green-400' };
        default:
            return { variant: 'secondary', class: '' };
    }
}

function money(value: number): string {
    return `₱${Number(value).toLocaleString('en-PH', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })}`;
}

function formatDateOnly(iso: string): string {
    return new Date(iso).toLocaleDateString('en-PH', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });
}

const rangeLabel = computed(() =>
    props.filters.from === props.filters.to
        ? formatDateOnly(props.filters.from)
        : `${formatDateOnly(props.filters.from)} – ${formatDateOnly(props.filters.to)}`,
);

// Stamped once per data load (this prop change, not a ticking clock) --
// never a setInterval/live clock, per the UI contract.
const generatedAt = ref(new Date());

watch(
    () => [props.selected, props.filters.from, props.filters.to] as const,
    () => {
        generatedAt.value = new Date();
    },
);

const generatedAtLabel = computed(() =>
    generatedAt.value.toLocaleString('en-PH', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }),
);

function selectReport(key: string): void {
    if (key === props.selected) {
        return;
    }

    router.get(
        props.indexUrl,
        { report: key, from: props.filters.from, to: props.filters.to },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

function onApplyRange({ from, to }: { from: string; to: string }): void {
    router.get(
        props.indexUrl,
        { report: props.selected, from, to },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}
</script>

<template>
    <div class="grid gap-6 xl:grid-cols-[300px_minmax(0,1fr)]">
        <div class="flex flex-col gap-4">
            <h2 class="text-[20px] leading-[1.2] font-semibold">
                Available Reports
            </h2>
            <div class="flex flex-col gap-4">
                <button
                    v-for="(report, key) in reports"
                    :key="key"
                    type="button"
                    :aria-pressed="key === selected"
                    class="bg-card text-card-foreground flex flex-col gap-1 rounded-xl border p-4 text-left shadow-sm transition-colors hover:bg-accent/50"
                    :class="
                        key === selected
                            ? 'border-primary'
                            : 'border-sidebar-border/70 dark:border-sidebar-border'
                    "
                    @click="selectReport(key)"
                >
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-sm font-semibold">{{ report.title }}</span>
                        <Badge variant="secondary">{{ report.badge }}</Badge>
                    </div>
                    <p class="text-muted-foreground text-sm">{{ report.subLine }}</p>
                    <span v-if="key === selected" class="sr-only">Selected</span>
                </button>
            </div>
        </div>

        <div class="flex flex-col gap-6">
            <Card>
                <CardHeader>
                    <CardTitle>Date Range</CardTitle>
                </CardHeader>
                <CardContent>
                    <DateRangeControl
                        :from="filters.from"
                        :to="filters.to"
                        @apply="onApplyRange"
                    />
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>{{ selectedReport?.title }}</CardTitle>
                    <CardDescription>{{ selectedReport?.subLine }}</CardDescription>
                </CardHeader>
                <CardContent class="flex flex-col gap-4">
                    <div class="flex flex-col gap-1">
                        <p class="text-muted-foreground text-sm">
                            Range: {{ rangeLabel }}
                        </p>
                        <p class="text-muted-foreground text-sm">
                            Generated {{ generatedAtLabel }}
                        </p>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <Button as="a" variant="outline" :href="exportPdfUrl(selected)">
                            <FileText class="size-4" />
                            Export PDF
                        </Button>
                        <Button
                            as="a"
                            variant="outline"
                            :href="exportXlsxUrl(selected)"
                        >
                            <FileSpreadsheet class="size-4" />
                            Export Excel
                        </Button>
                    </div>
                    <p class="text-muted-foreground text-sm">
                        Exports carry every row in this range and are recorded in
                        the audit trail.
                    </p>

                    <template v-if="selected === 'financial-summary'">
                        <div v-if="summary" class="flex flex-col gap-6">
                            <div class="flex flex-col gap-2">
                                <h3 class="text-[20px] leading-[1.2] font-semibold">
                                    Revenue
                                </h3>
                                <div class="flex items-center justify-between gap-4">
                                    <div class="flex flex-col">
                                        <span class="text-sm font-semibold">Job sales</span>
                                        <span class="text-muted-foreground text-sm">
                                            Down payments, balance payments, and full
                                            payments that cleared in this range.
                                        </span>
                                    </div>
                                    <span class="text-sm tabular-nums">
                                        {{ money(summary.job_sales) }}
                                    </span>
                                </div>
                                <div class="flex items-center justify-between gap-4">
                                    <div class="flex flex-col">
                                        <span class="text-sm font-semibold">
                                            Cancellation fees
                                        </span>
                                        <span class="text-muted-foreground text-sm">
                                            Fees collected on job orders that were
                                            cancelled.
                                        </span>
                                    </div>
                                    <span class="text-sm tabular-nums">
                                        {{ money(summary.cancellation_fees) }}
                                    </span>
                                </div>
                                <Separator />
                                <div class="flex items-center justify-between">
                                    <span class="text-sm font-semibold">Total revenue</span>
                                    <span class="text-sm tabular-nums">
                                        {{ money(summary.revenue_total) }}
                                    </span>
                                </div>
                            </div>

                            <div class="flex flex-col gap-2">
                                <h3 class="text-[20px] leading-[1.2] font-semibold">
                                    Expenses
                                </h3>
                                <div class="flex items-center justify-between gap-4">
                                    <div class="flex flex-col">
                                        <span class="text-sm font-semibold">
                                            Recorded expenses
                                        </span>
                                        <span class="text-muted-foreground text-sm">
                                            Every non-voided expense dated in this
                                            range.
                                        </span>
                                    </div>
                                    <span class="text-sm tabular-nums">
                                        {{ money(summary.expenses_total) }}
                                    </span>
                                </div>
                            </div>

                            <div class="flex flex-col gap-2">
                                <h3 class="text-[20px] leading-[1.2] font-semibold">
                                    Result
                                </h3>
                                <div class="flex flex-col gap-1">
                                    <span class="text-sm font-semibold">
                                        {{ summary.result < 0 ? 'Net Loss' : 'Net Profit' }}
                                    </span>
                                    <span
                                        class="text-[28px] leading-[1.2] font-semibold tabular-nums"
                                        :class="summary.result < 0 ? 'text-destructive' : ''"
                                    >
                                        {{ summary.result < 0 ? '-' : '' }}{{
                                            money(Math.abs(summary.result))
                                        }}
                                    </span>
                                </div>
                            </div>

                            <Alert v-if="summary.write_off_total > 0">
                                <Info class="size-4" />
                                <AlertTitle>
                                    Bad debt written off: {{ money(summary.write_off_total) }}
                                </AlertTitle>
                                <AlertDescription>
                                    This is not deducted above. Written-off balances
                                    were never counted as revenue in the first place,
                                    so subtracting them would book the same loss
                                    twice. Shown here so the figure isn't lost.
                                </AlertDescription>
                            </Alert>

                            <p class="text-muted-foreground text-sm">
                                Revenue counts money that actually arrived — a
                                payment is included on the day it was confirmed, not
                                the day it was started. Balances still on credit
                                contribute nothing until they're paid.
                            </p>
                        </div>
                    </template>

                    <template v-else>
                        <div
                            class="border-sidebar-border/70 dark:border-sidebar-border overflow-hidden rounded-xl border"
                        >
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead
                                            v-for="(header, idx) in columns"
                                            :key="header"
                                            :class="
                                                MONEY_FIELDS.has(rowFields[idx])
                                                    ? 'text-right'
                                                    : ''
                                            "
                                        >
                                            {{ header }}
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    <TableEmpty
                                        v-if="rows.length === 0"
                                        :colspan="columns.length"
                                    >
                                        <div class="flex flex-col items-center gap-1 text-center">
                                            <p class="font-semibold">Nothing in this range</p>
                                            <p class="text-muted-foreground">
                                                No
                                                {{ selectedReport?.title.toLowerCase() }}
                                                activity between
                                                {{ formatDateOnly(filters.from) }} and
                                                {{ formatDateOnly(filters.to) }}. Try a
                                                wider date range.
                                            </p>
                                        </div>
                                    </TableEmpty>
                                    <TableRow v-for="(row, idx) in rows" v-else :key="idx">
                                        <TableCell
                                            v-for="field in rowFields"
                                            :key="field"
                                            :class="cellClass(field)"
                                        >
                                            <template v-if="field === 'method'">
                                                {{ paymentMethodLabel(row[field]) }}
                                            </template>
                                            <template v-else-if="field === 'type'">
                                                {{ transactionTypeLabel(row[field]) }}
                                            </template>
                                            <template v-else-if="field === 'payment_status'">
                                                <Badge
                                                    :variant="paymentStatusBadgeProps(row[field]).variant"
                                                    :class="paymentStatusBadgeProps(row[field]).class"
                                                >
                                                    {{ paymentStatusLabel(row[field]) }}
                                                </Badge>
                                            </template>
                                            <template v-else-if="field === 'stage'">
                                                <Badge
                                                    :variant="jobOrderStageBadgeProps(row[field]).variant"
                                                    :class="jobOrderStageBadgeProps(row[field]).class"
                                                >
                                                    {{ jobOrderStageLabel(row[field]) }}
                                                </Badge>
                                            </template>
                                            <template v-else-if="field === 'status'">
                                                <Badge
                                                    v-if="row[field]"
                                                    variant="outline"
                                                    class="text-muted-foreground"
                                                >
                                                    {{ row[field] }}
                                                </Badge>
                                            </template>
                                            <template v-else-if="field === 'urgency'">
                                                <Badge
                                                    v-if="row[field]"
                                                    variant="outline"
                                                    class="border-amber-600/40 text-amber-600 dark:text-amber-400"
                                                >
                                                    <Zap class="size-3" />
                                                    Rush
                                                </Badge>
                                                <Badge
                                                    v-else
                                                    variant="outline"
                                                    class="border-green-600/40 text-green-600 dark:text-green-400"
                                                >
                                                    Normal
                                                </Badge>
                                            </template>
                                            <template v-else-if="field === 'cancellation_fee'">
                                                <span
                                                    v-if="Number(row[field] ?? 0) === 0"
                                                    class="text-muted-foreground"
                                                >
                                                    No fee
                                                </span>
                                                <span v-else>{{ moneyField(row[field]) }}</span>
                                            </template>
                                            <template v-else-if="MONEY_FIELDS.has(field)">
                                                {{ moneyField(row[field]) }}
                                            </template>
                                            <template
                                                v-else-if="
                                                    field === 'entered_production' ||
                                                    field === 'due'
                                                "
                                            >
                                                {{ dateField(row[field]) }}
                                            </template>
                                            <template v-else-if="field === 'date'">
                                                {{ formatDateOnly(String(row[field])) }}
                                            </template>
                                            <template v-else>
                                                {{ displayValue(row[field]) }}
                                            </template>
                                        </TableCell>
                                    </TableRow>
                                </TableBody>
                                <TableFooter v-if="rowsTotal > 100">
                                    <TableRow>
                                        <TableCell
                                            :colspan="columns.length"
                                            class="text-muted-foreground text-sm"
                                        >
                                            Showing the first 100 of {{ rowsTotal }} rows.
                                            Export to see them all.
                                        </TableCell>
                                    </TableRow>
                                </TableFooter>
                            </Table>
                        </div>
                    </template>
                </CardContent>
            </Card>
        </div>
    </div>
</template>

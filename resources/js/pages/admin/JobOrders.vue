<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { FileSpreadsheet, FileText, Filter, Search, Zap } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import JobOrderTotal from '@/components/JobOrderTotal.vue';
import DataTableCard from '@/components/DataTableCard.vue';
import EmptyState from '@/components/EmptyState.vue';
import PageContainer from '@/components/PageContainer.vue';
import PageHeader from '@/components/PageHeader.vue';
import SearchableSelect, {
    type SearchableOption,
} from '@/components/SearchableSelect.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import TablePagination from '@/components/TablePagination.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
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
import { adminNavItems } from '@/config/nav/admin';
import {
    balanceLabel,
    jobOrderStatusBadge,
    jobOrderStatusLabel,
    money,
    paymentStatusBadge,
    paymentStatusLabel,
} from '@/lib/jobOrders';
import { index as jobOrdersIndex } from '@/routes/admin/job-orders';
import {
    pdf as jobOrdersExportPdf,
    xlsx as jobOrdersExportXlsx,
} from '@/routes/admin/job-orders/export';

interface AdminJobOrder {
    id: number;
    number: string | null;
    description: string;
    width_ft: string | null;
    height_ft: string | null;
    quantity: number | null;
    display_status: string;
    payment_status: string;
    total_amount: number | null;
    display_total: number | null;
    amount_paid: number | null;
    is_rush: boolean;
    queue_entry: { customer: { name: string } | null } | null;
    pricing_entry: { id: number; name: string } | null;
}

/** Shape of Laravel's LengthAwarePaginator::toArray(), passed through as-is. */
interface PaginatedJobOrders {
    data: AdminJobOrder[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}

const props = defineProps<{
    jobOrders: PaginatedJobOrders;
    filters: {
        q?: string;
        status?: string;
        payment_status?: string;
        from?: string;
        to?: string;
    };
    statuses: string[];
    paymentStatuses: string[];
}>();

// The current page of results follows the shop as orders move and get paid.
// A poll reloads the URL as it stands, so the filters and page stay put.
useLivePoll(['jobOrders']);

defineOptions({
    layout: {
        navItems: adminNavItems,
        breadcrumbs: [
            {
                title: 'Job Orders',
                href: jobOrdersIndex(),
            },
        ],
    },
});

const ALL = 'all';

const searchTerm = ref(props.filters.q ?? '');
const selectedStatus = ref(props.filters.status ?? ALL);
const selectedPaymentStatus = ref(props.filters.payment_status ?? ALL);
const fromDate = ref(props.filters.from ?? '');
const toDate = ref(props.filters.to ?? '');
const searching = ref(false);

const statusOptions = computed<SearchableOption[]>(() => [
    { value: ALL, label: 'All statuses' },
    ...props.statuses.map((status) => ({
        value: status,
        label: jobOrderStatusLabel(status),
    })),
]);

const paymentStatusOptions = computed<SearchableOption[]>(() => [
    { value: ALL, label: 'All payment statuses' },
    ...props.paymentStatuses.map((status) => ({
        value: status,
        label: paymentStatusLabel(status),
    })),
]);

function visit(page?: number): void {
    router.get(
        jobOrdersIndex.url(),
        {
            ...(searchTerm.value.trim() !== ''
                ? { q: searchTerm.value.trim() }
                : {}),
            ...(selectedStatus.value !== ALL
                ? { status: selectedStatus.value }
                : {}),
            ...(selectedPaymentStatus.value !== ALL
                ? { payment_status: selectedPaymentStatus.value }
                : {}),
            ...(fromDate.value ? { from: fromDate.value } : {}),
            ...(toDate.value ? { to: toDate.value } : {}),
            ...(page ? { page } : {}),
        },
        {
            preserveState: true,
            // A new page starts at the top; a filter change keeps its place.
            preserveScroll: !page,
            replace: true,
            onStart: () => (searching.value = true),
            onFinish: () => (searching.value = false),
        },
    );
}

function clearFilters(): void {
    searchTerm.value = '';
    selectedStatus.value = ALL;
    selectedPaymentStatus.value = ALL;
    fromDate.value = '';
    toDate.value = '';
}

let searchTimer: ReturnType<typeof setTimeout> | undefined;

// Debounced so an Admin typing a job order number or customer name fires
// one request instead of one per keystroke.
watch(searchTerm, () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => visit(), 300);
});

// One watcher for the instant filters, so "Clear filters" resetting all of
// them at once is a single request. It also drops a pending search debounce,
// which this visit already carries.
watch([selectedStatus, selectedPaymentStatus, fromDate, toDate], () => {
    clearTimeout(searchTimer);
    visit();
});

const exportQuery = computed(() => ({
    ...(searchTerm.value.trim() !== '' ? { q: searchTerm.value.trim() } : {}),
    ...(selectedStatus.value !== ALL ? { status: selectedStatus.value } : {}),
    ...(selectedPaymentStatus.value !== ALL
        ? { payment_status: selectedPaymentStatus.value }
        : {}),
    ...(fromDate.value ? { from: fromDate.value } : {}),
    ...(toDate.value ? { to: toDate.value } : {}),
}));

const exportPdfUrl = computed(() =>
    jobOrdersExportPdf.url({ query: exportQuery.value }),
);
const exportXlsxUrl = computed(() =>
    jobOrdersExportXlsx.url({ query: exportQuery.value }),
);

function sizeLabel(jobOrder: AdminJobOrder): string {
    if (jobOrder.width_ft && jobOrder.height_ft) {
        const quantity = jobOrder.quantity ?? 1;

        return `${jobOrder.width_ft}ft × ${jobOrder.height_ft}ft × ${quantity}`;
    }

    if (jobOrder.quantity) {
        return String(jobOrder.quantity);
    }

    return '—';
}
</script>

<template>
    <Head title="Job Orders" />

    <PageContainer>
        <PageHeader
            title="Job Orders"
            description="Every job order across every stage, with the price the shop is quoting or has charged. Read-only — make changes from the role portal that owns each order."
        >
            <template #actions>
                <Button
                    as="a"
                    variant="outline"
                    :href="exportPdfUrl"
                    data-test="export-job-orders-pdf-button"
                >
                    <FileText class="size-4" />
                    Export PDF
                </Button>
                <Button
                    as="a"
                    variant="outline"
                    :href="exportXlsxUrl"
                    data-test="export-job-orders-xlsx-button"
                >
                    <FileSpreadsheet class="size-4" />
                    Export Excel
                </Button>
            </template>
        </PageHeader>

        <Card>
            <CardHeader :icon="Filter">
                <CardTitle>Filters</CardTitle>
            </CardHeader>
            <CardContent>
                <div class="grid gap-4 @md:grid-cols-2 @3xl:grid-cols-3">
                    <div class="flex min-w-0 flex-col gap-2">
                        <Label for="job-order-search">
                            Search by number or customer
                        </Label>
                        <div class="relative">
                            <Search
                                class="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2"
                            />
                            <Input
                                id="job-order-search"
                                v-model="searchTerm"
                                type="search"
                                class="pl-9"
                                placeholder="JO-2026-1234 or Maria Santos"
                                autocomplete="off"
                            />
                        </div>
                    </div>

                    <div class="flex min-w-0 flex-col gap-2">
                        <Label for="job-order-status-filter">Status</Label>
                        <SearchableSelect
                            id="job-order-status-filter"
                            v-model="selectedStatus"
                            :options="statusOptions"
                            placeholder="All statuses"
                        />
                    </div>

                    <div class="flex min-w-0 flex-col gap-2">
                        <Label for="job-order-payment-status-filter">
                            Payment Status
                        </Label>
                        <SearchableSelect
                            id="job-order-payment-status-filter"
                            v-model="selectedPaymentStatus"
                            :options="paymentStatusOptions"
                            placeholder="All payment statuses"
                        />
                    </div>

                    <div class="flex min-w-0 flex-col gap-2">
                        <Label for="job-order-from-filter">From</Label>
                        <Input
                            id="job-order-from-filter"
                            v-model="fromDate"
                            type="date"
                        />
                    </div>

                    <div class="flex min-w-0 flex-col gap-2">
                        <Label for="job-order-to-filter">To</Label>
                        <Input
                            id="job-order-to-filter"
                            v-model="toDate"
                            type="date"
                        />
                    </div>

                    <div class="flex min-w-0 flex-col justify-end gap-2">
                        <Button
                            type="button"
                            variant="secondary"
                            data-test="clear-job-order-filters-button"
                            @click="clearFilters"
                        >
                            Clear filters
                        </Button>
                    </div>
                </div>
            </CardContent>
        </Card>

        <DataTableCard>
            <div class="w-full overflow-x-auto">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>#</TableHead>
                            <TableHead>Customer</TableHead>
                            <TableHead>Product / Service</TableHead>
                            <TableHead class="text-right">Size × Qty</TableHead>
                            <TableHead class="text-right">Total</TableHead>
                            <TableHead class="text-right">Paid</TableHead>
                            <TableHead class="text-right">Balance</TableHead>
                            <TableHead>Status</TableHead>
                            <TableHead>Payment</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableEmpty
                            v-if="jobOrders.data.length === 0"
                            :colspan="9"
                        >
                            <EmptyState
                                title="No job orders match that"
                                description="Check the spelling, or clear the status filter to widen the search."
                            />
                        </TableEmpty>
                        <TableRow
                            v-for="jobOrder in jobOrders.data"
                            v-else
                            :key="jobOrder.id"
                            :class="{
                                'bg-warning/10 hover:bg-warning/15':
                                    jobOrder.is_rush,
                            }"
                        >
                            <TableCell class="font-medium tabular-nums">
                                <div class="flex items-center gap-2">
                                    {{ jobOrder.number ?? '—' }}
                                    <Badge
                                        v-if="jobOrder.is_rush"
                                        variant="outline"
                                        class="border-brand/40 text-brand"
                                    >
                                        <Zap class="size-3" />
                                        Rush
                                    </Badge>
                                </div>
                            </TableCell>
                            <TableCell>
                                {{
                                    jobOrder.queue_entry?.customer?.name ?? '—'
                                }}
                            </TableCell>
                            <TableCell>
                                {{
                                    jobOrder.pricing_entry?.name ??
                                    jobOrder.description
                                }}
                            </TableCell>
                            <TableCell class="text-right tabular-nums">
                                {{ sizeLabel(jobOrder) }}
                            </TableCell>
                            <TableCell class="text-right tabular-nums">
                                <JobOrderTotal :job-order="jobOrder" />
                            </TableCell>
                            <TableCell class="text-right tabular-nums">
                                {{ money(jobOrder.amount_paid) }}
                            </TableCell>
                            <TableCell class="text-right tabular-nums">
                                {{ balanceLabel(jobOrder) }}
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
                        </TableRow>
                    </TableBody>
                </Table>
            </div>
        </DataTableCard>

        <TablePagination :paginator="jobOrders" @update:page="visit" />
    </PageContainer>
</template>

<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Filter, Search } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import JobOrderTotal from '@/components/JobOrderTotal.vue';
import DataTableCard from '@/components/DataTableCard.vue';
import EmptyState from '@/components/EmptyState.vue';
import PageContainer from '@/components/PageContainer.vue';
import PageHeader from '@/components/PageHeader.vue';
import SearchableSelect, {
    type SearchableOption,
} from '@/components/SearchableSelect.vue';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Pagination,
    PaginationContent,
    PaginationEllipsis,
    PaginationFirst,
    PaginationItem,
    PaginationLast,
    PaginationNext,
    PaginationPrevious,
} from '@/components/ui/pagination';
import {
    Table,
    TableBody,
    TableCell,
    TableEmpty,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { adminNavItems } from '@/config/nav/admin';
import {
    balanceLabel,
    jobOrderStatusLabel,
    money,
    paymentStatusLabel,
} from '@/lib/jobOrders';
import { index as jobOrdersIndex } from '@/routes/admin/job-orders';

interface AdminJobOrder {
    id: number;
    number: string | null;
    description: string;
    width_ft: string | null;
    height_ft: string | null;
    quantity: number | null;
    status: string;
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
    filters: { q?: string; status?: string };
    statuses: string[];
}>();

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
const searching = ref(false);

const statusOptions = computed<SearchableOption[]>(() => [
    { value: ALL, label: 'All statuses' },
    ...props.statuses.map((status) => ({
        value: status,
        label: jobOrderStatusLabel(status),
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
            ...(page ? { page } : {}),
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            onStart: () => (searching.value = true),
            onFinish: () => (searching.value = false),
        },
    );
}

let searchTimer: ReturnType<typeof setTimeout> | undefined;

// Debounced so an Admin typing a job order number or customer name fires
// one request instead of one per keystroke.
watch(searchTerm, () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => visit(), 300);
});

watch(selectedStatus, () => visit());

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
        />

        <Card>
            <CardHeader :icon="Filter">
                <CardTitle>Filters</CardTitle>
            </CardHeader>
            <CardContent>
                <div class="grid gap-4 sm:grid-cols-2">
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
                        >
                            <TableCell class="font-medium tabular-nums">
                                <div class="flex items-center gap-2">
                                    {{ jobOrder.number ?? '—' }}
                                    <Badge
                                        v-if="jobOrder.is_rush"
                                        variant="outline"
                                    >
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
                                <Badge variant="secondary">
                                    {{ jobOrderStatusLabel(jobOrder.status) }}
                                </Badge>
                            </TableCell>
                            <TableCell>
                                <Badge variant="outline">
                                    {{
                                        paymentStatusLabel(
                                            jobOrder.payment_status,
                                        )
                                    }}
                                </Badge>
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>
        </DataTableCard>

        <Pagination
            v-if="jobOrders.last_page > 1"
            v-slot="{ page }"
            :page="jobOrders.current_page"
            :items-per-page="jobOrders.per_page"
            :total="jobOrders.total"
            :sibling-count="1"
            show-edges
            @update:page="visit"
        >
            <PaginationContent v-slot="{ items }">
                <PaginationFirst />
                <PaginationPrevious />

                <template v-for="(item, index) in items">
                    <PaginationItem
                        v-if="item.type === 'page'"
                        :key="index"
                        :value="item.value"
                        :is-active="item.value === page"
                    >
                        {{ item.value }}
                    </PaginationItem>
                    <PaginationEllipsis
                        v-else
                        :key="`ellipsis-${index}`"
                        :index="index"
                    />
                </template>

                <PaginationNext />
                <PaginationLast />
            </PaginationContent>
        </Pagination>
    </PageContainer>
</template>

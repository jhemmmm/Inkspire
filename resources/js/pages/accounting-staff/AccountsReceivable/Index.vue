<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Clock } from '@lucide/vue';
import { computed, ref } from 'vue';
import DataTableCard from '@/components/DataTableCard.vue';
import EmptyState from '@/components/EmptyState.vue';
import PageContainer from '@/components/PageContainer.vue';
import PageHeader from '@/components/PageHeader.vue';
import StatCard from '@/components/StatCard.vue';
import TableFilterBar from '@/components/TableFilterBar.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
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
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { useLivePoll } from '@/composables/useLivePoll';
import { useBusinessTime } from '@/composables/useBusinessTime';
import { useTableFilter } from '@/composables/useTableFilter';
import { accountingStaffNavItems } from '@/config/nav/accounting-staff';
import {
    agingBadge,
    BRACKET_LABELS,
    COLLECTION_STATUS_LABELS,
    collectionStatusBadge,
} from '@/lib/accountsReceivable';
import { money } from '@/lib/jobOrders';
import {
    index as accountsReceivableIndex,
    show,
} from '@/routes/accounting-staff/accounts-receivable';

interface AccountsReceivableRow {
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
}

interface BracketSummary {
    bracket: string;
    total: number;
    count: number;
}

const props = defineProps<{
    receivables: AccountsReceivableRow[];
    closedReceivables: AccountsReceivableRow[];
    bracketSummaries: BracketSummary[];
}>();

// Payments the Cashier takes and credit an Admin approves change these
// balances while the page is open.
useLivePoll(['receivables', 'closedReceivables', 'bracketSummaries']);

defineOptions({
    layout: {
        navItems: accountingStaffNavItems,
        breadcrumbs: [
            {
                title: 'Accounts Receivable',
                href: accountsReceivableIndex(),
            },
        ],
    },
});

const BRACKETS = [
    'current',
    'one_to_fifteen',
    'sixteen_to_thirty',
    'thirty_one_to_sixty',
    'sixty_one_to_ninety',
    'ninety_plus',
] as const;

type FilterValue = 'all' | 'closed' | (typeof BRACKETS)[number];

const activeFilter = ref<FilterValue>('all');

function onTabChange(value: unknown): void {
    activeFilter.value = value as FilterValue;
}

const bracketSummaryByKey = computed(() =>
    Object.fromEntries(
        props.bracketSummaries.map((summary) => [summary.bracket, summary]),
    ),
);

/**
 * "1 entry" / "4 entries" -- pluralised here rather than inline in the
 * template so the ageing tiles can pass it as a plain string prop.
 */
function entryCountLabel(count: number): string {
    return `${count} ${count === 1 ? 'entry' : 'entries'}`;
}

function summaryFor(bracket: string): BracketSummary {
    return (
        bracketSummaryByKey.value[bracket] ?? { bracket, total: 0, count: 0 }
    );
}

const filteredRows = computed(() => {
    if (activeFilter.value === 'closed') {
        return props.closedReceivables;
    }

    if (activeFilter.value === 'all') {
        return props.receivables;
    }

    return props.receivables.filter(
        (row) => row.aging_bracket === activeFilter.value,
    );
});

const ALL = 'all';

/**
 * Derived from every row on the page (open + closed), not just the
 * currently selected tab — so the option list doesn't reshuffle as the
 * Admin switches tabs (D3).
 */
const collectionStatusOptions = computed(() => {
    const statuses = Array.from(
        new Set(
            [...props.receivables, ...props.closedReceivables].map(
                (row) => row.collection_status,
            ),
        ),
    ).sort((a, b) => a.localeCompare(b));

    return [
        { value: ALL, label: 'All collection statuses' },
        ...statuses.map((status) => ({
            value: status,
            label: COLLECTION_STATUS_LABELS[status] ?? status,
        })),
    ];
});

const collectionStatusFilter = ref(ALL);

/**
 * Search + the new Collection status filter apply ON TOP of the existing
 * bracket/closed tab filter (D2) — `filteredRows` (the tab layer) is the
 * input here, not `props.receivables` directly.
 */
const { searchTerm, filtered: visibleRows } = useTableFilter(
    () => filteredRows.value,
    (row) => [
        row.job_order.number,
        row.job_order.queue_entry.customer?.name,
        row.job_order.description,
    ],
    {
        filters: [
            (row) =>
                collectionStatusFilter.value === ALL ||
                row.collection_status === collectionStatusFilter.value,
        ],
    },
);

const filtersActive = computed(
    () =>
        searchTerm.value.trim() !== '' ||
        collectionStatusFilter.value !== ALL ||
        activeFilter.value !== 'all',
);

/** Resets the tab filter too (D2), in addition to search and the select. */
function clearFilters(): void {
    searchTerm.value = '';
    collectionStatusFilter.value = ALL;
    activeFilter.value = 'all';
}

const { formatInstant } = useBusinessTime();

function dueDateLabel(dueAt: string | null): string {
    if (!dueAt) {
        return '—';
    }

    return formatInstant(dueAt, {
        month: 'long',
        day: 'numeric',
        year: 'numeric',
    });
}

function dueSubLine(row: AccountsReceivableRow): string {
    if (row.days_past_due !== null) {
        return `${row.days_past_due} day${row.days_past_due === 1 ? '' : 's'} past due`;
    }

    if (!row.due_at) {
        return '';
    }

    const daysUntilDue = Math.ceil(
        (new Date(row.due_at).getTime() - Date.now()) / (1000 * 60 * 60 * 24),
    );

    if (daysUntilDue <= 0) {
        return '';
    }

    return `Due in ${daysUntilDue} day${daysUntilDue === 1 ? '' : 's'}`;
}
</script>

<template>
    <Head title="Accounts Receivable" />

    <PageContainer>
        <PageHeader
            title="Accounts Receivable"
            description="Admin-approved credit balances, grouped by how far past due they are. Older brackets need chasing first."
        />

        <!--
            These totals always summarise the whole open aging set, not the
            search/collection-status filter below — they describe the shop's
            receivables work, not the current view (D4).
        -->
        <div
            class="grid grid-cols-1 gap-4 @sm:grid-cols-2 @2xl:grid-cols-3 @6xl:grid-cols-6"
        >
            <StatCard
                v-for="bracket in BRACKETS"
                :key="bracket"
                :label="BRACKET_LABELS[bracket]"
                :value="money(summaryFor(bracket).total)"
                :tone="bracket === 'ninety_plus' ? 'attention' : 'default'"
                :hint="entryCountLabel(summaryFor(bracket).count)"
                ink="yellow"
            />
        </div>

        <Tabs :model-value="activeFilter" @update:model-value="onTabChange">
            <!--
                A trigger's default height is 100% of the list, so in a
                wrapped list each one stretched to the height of every row
                combined. A fixed height keeps them the size of one row.
            -->
            <TabsList class="h-auto max-w-full flex-wrap justify-start gap-1">
                <TabsTrigger value="all" class="h-10 flex-none px-3">
                    All
                </TabsTrigger>
                <TabsTrigger
                    v-for="bracket in BRACKETS"
                    :key="bracket"
                    :value="bracket"
                    class="h-10 flex-none px-3"
                >
                    {{ BRACKET_LABELS[bracket] }}
                </TabsTrigger>
                <TabsTrigger value="closed" class="h-10 flex-none px-3">
                    Closed
                </TabsTrigger>
            </TabsList>
        </Tabs>

        <TableFilterBar
            v-if="receivables.length > 0 || closedReceivables.length > 0"
            v-model:search="searchTerm"
            search-label="Search receivables"
            search-placeholder="Job order, customer or description"
            :shown="visibleRows.length"
            :total="filteredRows.length"
            :active="filtersActive"
            @clear="clearFilters"
        >
            <div class="flex min-w-0 flex-col gap-2 @lg:w-56">
                <Label for="receivable-collection-status-filter">
                    Collection status
                </Label>
                <Select v-model="collectionStatusFilter">
                    <SelectTrigger
                        id="receivable-collection-status-filter"
                        class="w-full"
                    >
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="option in collectionStatusOptions"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>
        </TableFilterBar>

        <DataTableCard>
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Job Order</TableHead>
                        <TableHead>Customer</TableHead>
                        <TableHead class="text-right">Total</TableHead>
                        <TableHead class="text-right">Paid</TableHead>
                        <TableHead class="text-right">Outstanding</TableHead>
                        <TableHead>Due</TableHead>
                        <TableHead>Aging</TableHead>
                        <TableHead>Collection Status</TableHead>
                        <TableHead class="text-right">Actions</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableEmpty v-if="filteredRows.length === 0" :colspan="9">
                        <div
                            v-if="
                                activeFilter === 'all' &&
                                receivables.length === 0
                            "
                            class="flex flex-col items-center gap-1 text-center"
                        >
                            <p class="font-semibold">
                                No outstanding receivables
                            </p>
                            <p class="text-muted-foreground">
                                Balances appear here once an Admin approves an
                                On-Credit request at the counter.
                            </p>
                        </div>
                        <p v-else-if="activeFilter === 'closed'">
                            No settled or written-off entries yet.
                        </p>
                        <p v-else>
                            Nothing in
                            {{ BRACKET_LABELS[activeFilter] ?? activeFilter }}.
                        </p>
                    </TableEmpty>
                    <TableEmpty
                        v-else-if="visibleRows.length === 0"
                        :colspan="9"
                    >
                        <EmptyState
                            title="No matches"
                            description="No receivables in this view match that search or collection status filter."
                        >
                            <template #actions>
                                <Button
                                    type="button"
                                    variant="secondary"
                                    data-test="clear-receivable-filters-button"
                                    @click="clearFilters"
                                >
                                    Clear filters
                                </Button>
                            </template>
                        </EmptyState>
                    </TableEmpty>
                    <TableRow v-for="row in visibleRows" v-else :key="row.id">
                        <TableCell>
                            <div class="flex flex-col">
                                <span
                                    class="text-muted-foreground text-xs tabular-nums"
                                >
                                    {{ row.job_order.number ?? '—' }}
                                </span>
                                <span>{{ row.job_order.description }}</span>
                            </div>
                        </TableCell>
                        <TableCell>
                            {{
                                row.job_order.queue_entry.customer?.name ?? '—'
                            }}
                        </TableCell>
                        <TableCell class="text-right tabular-nums">
                            {{ money(row.job_order.total_amount ?? 0) }}
                        </TableCell>
                        <TableCell class="text-right tabular-nums">
                            {{
                                money(
                                    (row.job_order.total_amount ?? 0) -
                                        row.balance,
                                )
                            }}
                        </TableCell>
                        <TableCell class="text-right tabular-nums">
                            <span v-if="row.balance <= 0" class="text-success">
                                Settled
                            </span>
                            <span v-else>{{ money(row.balance) }}</span>
                        </TableCell>
                        <TableCell>
                            <div class="flex flex-col">
                                <span>{{ dueDateLabel(row.due_at) }}</span>
                                <span
                                    v-if="dueSubLine(row)"
                                    class="text-muted-foreground text-sm"
                                >
                                    {{ dueSubLine(row) }}
                                </span>
                            </div>
                        </TableCell>
                        <TableCell>
                            <StatusBadge :tone="agingBadge(row.aging_bracket)">
                                {{
                                    BRACKET_LABELS[row.aging_bracket] ??
                                    row.aging_bracket
                                }}
                            </StatusBadge>
                        </TableCell>
                        <TableCell>
                            <div class="flex flex-col items-start gap-1">
                                <StatusBadge
                                    :tone="
                                        collectionStatusBadge(
                                            row.collection_status,
                                        )
                                    "
                                >
                                    {{
                                        COLLECTION_STATUS_LABELS[
                                            row.collection_status
                                        ] ?? row.collection_status
                                    }}
                                </StatusBadge>
                                <Badge
                                    v-if="row.write_off_requested_at"
                                    variant="secondary"
                                >
                                    <Clock class="size-3" />
                                    Write-Off Pending
                                </Badge>
                            </div>
                        </TableCell>
                        <TableCell class="text-right">
                            <Button as-child variant="outline" size="sm">
                                <Link
                                    :href="show.url(row.id)"
                                    :data-test="`view-entry-${row.id}-link`"
                                >
                                    View Entry
                                </Link>
                            </Button>
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </DataTableCard>
    </PageContainer>
</template>

<script setup lang="ts">
import { Form, Head, router, usePoll } from '@inertiajs/vue3';
import { Zap } from '@lucide/vue';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import ProductionStageController from '@/actions/App/Http/Controllers/ProductionStaff/ProductionStageController';
import AlertError from '@/components/AlertError.vue';
import DataTableCard from '@/components/DataTableCard.vue';
import EmptyState from '@/components/EmptyState.vue';
import PageContainer from '@/components/PageContainer.vue';
import PageHeader from '@/components/PageHeader.vue';
import TableFilterBar from '@/components/TableFilterBar.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
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
import { useTableFilter } from '@/composables/useTableFilter';
import { productionStaffNavItems } from '@/config/nav/production-staff';
import { paymentStatusLabel } from '@/lib/jobOrders';
import { dashboard } from '@/routes/production-staff';

interface ProductionJobOrder {
    id: number;
    number: string | null;
    description: string;
    status: string;
    due_at: string | null;
    is_rush: boolean;
    payment_status: string;
    cleared_for_production: boolean;
    queue_entry_id: number;
    queue_entry: { customer: { name: string } };
}

const props = defineProps<{
    jobOrders: ProductionJobOrder[];
}>();

defineOptions({
    layout: {
        navItems: productionStaffNavItems,
        breadcrumbs: [
            {
                title: 'Production Board',
                href: dashboard(),
            },
        ],
    },
});

// D-14: the board self-corrects on every 5-second poll, matching the other
// two polled surfaces built in this phase.
usePoll(5000, { only: ['jobOrders'] });

type TabValue = 'to_print' | 'awaiting_payment' | 'done' | 'all';

const tabs: {
    value: TabValue;
    label: string;
    matches: (jobOrder: ProductionJobOrder) => boolean;
    emptyTitle: string;
    emptyDescription: string;
}[] = [
    {
        value: 'to_print',
        label: 'To Print',
        matches: (jobOrder) =>
            jobOrder.cleared_for_production &&
            jobOrder.status !== 'ready_for_pickup',
        emptyTitle: 'Nothing to print',
        emptyDescription:
            'Paid or down-paid orders waiting to be printed appear here.',
    },
    {
        value: 'awaiting_payment',
        label: 'Awaiting Payment',
        matches: (jobOrder) =>
            !jobOrder.cleared_for_production &&
            jobOrder.status !== 'ready_for_pickup',
        emptyTitle: 'No orders waiting on payment',
        emptyDescription:
            'Approved orders the customer has not paid for yet appear here, locked until the Cashier records a payment.',
    },
    {
        value: 'done',
        label: 'Done',
        matches: (jobOrder) => jobOrder.status === 'ready_for_pickup',
        emptyTitle: 'Nothing waiting for pickup',
        emptyDescription:
            'Orders you mark Done wait here until the counter releases them.',
    },
    {
        value: 'all',
        label: 'All',
        matches: () => true,
        emptyTitle: 'Nothing in production',
        emptyDescription:
            'Job orders appear here automatically once a file is validated or a design is approved.',
    },
];

const activeTab = ref<TabValue>('to_print');

const tabCounts = computed(
    () =>
        Object.fromEntries(
            tabs.map((tab) => [
                tab.value,
                props.jobOrders.filter((jobOrder) => tab.matches(jobOrder))
                    .length,
            ]),
        ) as Record<TabValue, number>,
);

const activeTabConfig = computed(
    () => tabs.find((tab) => tab.value === activeTab.value) ?? tabs[0],
);

const tabJobOrders = computed(() =>
    props.jobOrders.filter((jobOrder) =>
        activeTabConfig.value.matches(jobOrder),
    ),
);

function onTabChange(value: unknown): void {
    activeTab.value = value as TabValue;
}

const rushJobOrders = computed(() =>
    props.jobOrders.filter((jobOrder) => jobOrder.is_rush),
);

/**
 * Search applies on top of the tab filter — the whole board is a handful of
 * rows, so filtering is always client-side.
 */
const { searchTerm, filtered: visibleJobOrders } = useTableFilter(
    () => tabJobOrders.value,
    (jobOrder) => [
        jobOrder.number,
        jobOrder.queue_entry?.customer?.name,
        jobOrder.description,
    ],
);

const filtersActive = computed(() => searchTerm.value.trim() !== '');

function clearFilters(): void {
    searchTerm.value = '';
}

const STAGE_LABELS: Record<string, string> = {
    for_production: 'For Production',
    printing: 'Printing',
    quality_check: 'Quality Check',
    ready_for_pickup: 'Ready for Pickup',
};

function dueTimeOnly(dueAt: string | null): string {
    if (!dueAt) {
        return '—';
    }

    return new Date(dueAt).toLocaleTimeString('en-PH', {
        hour: 'numeric',
        minute: '2-digit',
    });
}

/**
 * "Today" / "Tomorrow" / a full date for anything further out. The time is
 * rendered on its own line beneath it, which keeps the column narrow.
 */
function dueDay(dueAt: string | null): string {
    if (!dueAt) {
        return '—';
    }

    const date = new Date(dueAt);
    const now = new Date();

    if (date.toDateString() === now.toDateString()) {
        return 'Today';
    }

    const tomorrow = new Date(now);
    tomorrow.setDate(tomorrow.getDate() + 1);

    if (date.toDateString() === tomorrow.toDateString()) {
        return 'Tomorrow';
    }

    return date.toLocaleDateString('en-PH', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });
}

function isOverdue(dueAt: string | null): boolean {
    if (!dueAt) {
        return false;
    }

    return new Date(dueAt).getTime() < Date.now();
}

/**
 * "{n} rush order{s} on the board" — the rush banner's title.
 */
function rushBannerTitle(): string {
    const count = rushJobOrders.value.length;

    return `${count} rush order${count === 1 ? '' : 's'} on the board`;
}

/**
 * Names the first two rush job orders with their due time, appending
 * "and {n} more" only once a third rush order exists.
 */
function rushBannerBody(): string {
    const items = rushJobOrders.value.slice(0, 2);
    const names = items.map(
        (jobOrder) =>
            `${jobOrder.number ?? '—'} (due ${dueTimeOnly(jobOrder.due_at)})`,
    );
    const extra = rushJobOrders.value.length - names.length;

    let subject = names.join(', ');

    if (extra > 0) {
        subject += ` and ${extra} more`;
    }

    return `${subject}. Work these first.`;
}

/**
 * A failed-move message (stale stage, "Awaiting payment", cancelled...) set
 * from the server's flashed error toast — kept as a page-level Alert in
 * addition to the global toast, since a toast can be dismissed or missed
 * before it's read. Cleared on the next flash of any kind.
 */
const failedMoveMessage = ref<string | null>(null);
let removeFlashListener: (() => void) | undefined;

onMounted(() => {
    removeFlashListener = router.on('flash', (event) => {
        const flash = (event as CustomEvent).detail?.flash;
        const data = flash?.toast as
            | { type: string; message: string }
            | undefined;

        failedMoveMessage.value = data?.type === 'error' ? data.message : null;
    });
});

onUnmounted(() => {
    removeFlashListener?.();
});
</script>

<template>
    <Head title="Production Board" />

    <PageContainer>
        <PageHeader
            title="Production Board"
            description="Print what is paid for. Start an order when it hits the press, mark it Done when it is ready for the counter, and Undo if you tapped too soon."
        />

        <Alert v-if="rushJobOrders.length > 0" variant="default">
            <Zap class="size-4 text-amber-600 dark:text-amber-400" />
            <AlertTitle class="text-amber-600 dark:text-amber-400">
                {{ rushBannerTitle() }}
            </AlertTitle>
            <AlertDescription>
                {{ rushBannerBody() }}
            </AlertDescription>
        </Alert>

        <AlertError
            v-if="failedMoveMessage"
            title="This move didn't go through"
            :errors="[failedMoveMessage]"
        />

        <Tabs :model-value="activeTab" @update:model-value="onTabChange">
            <!--
                Wraps rather than running off the edge on a phone. A trigger's
                default height is 100% of the list, so each gets a fixed
                height or a wrapped list stretches them.
            -->
            <TabsList class="h-auto max-w-full flex-wrap justify-start">
                <TabsTrigger
                    v-for="tab in tabs"
                    :key="tab.value"
                    :value="tab.value"
                    class="h-8 flex-none"
                    :data-test="`production-tab-${tab.value}`"
                >
                    {{ tab.label }}
                    <span class="tabular-nums"
                        >({{ tabCounts[tab.value] }})</span
                    >
                </TabsTrigger>
            </TabsList>
        </Tabs>

        <TableFilterBar
            v-if="tabJobOrders.length > 0"
            v-model:search="searchTerm"
            search-label="Search job orders"
            search-placeholder="Job order, customer or description"
            :shown="visibleJobOrders.length"
            :total="tabJobOrders.length"
            :active="filtersActive"
            @clear="clearFilters"
        />

        <DataTableCard>
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Job Order</TableHead>
                        <TableHead>Description</TableHead>
                        <TableHead>Status</TableHead>
                        <TableHead>Due</TableHead>
                        <TableHead class="text-right">Actions</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableEmpty v-if="tabJobOrders.length === 0" :colspan="5">
                        <EmptyState
                            :title="activeTabConfig.emptyTitle"
                            :description="activeTabConfig.emptyDescription"
                        />
                    </TableEmpty>
                    <TableEmpty
                        v-else-if="visibleJobOrders.length === 0"
                        :colspan="5"
                    >
                        <EmptyState
                            title="No matches"
                            description="No job orders in this tab match that search."
                        >
                            <template #actions>
                                <Button
                                    type="button"
                                    variant="secondary"
                                    data-test="clear-production-filters-button"
                                    @click="clearFilters"
                                >
                                    Clear search
                                </Button>
                            </template>
                        </EmptyState>
                    </TableEmpty>
                    <TableRow
                        v-for="jobOrder in visibleJobOrders"
                        v-else
                        :key="jobOrder.id"
                        :class="{
                            'bg-amber-50 dark:bg-amber-950/20':
                                jobOrder.is_rush,
                        }"
                    >
                        <!--
                            Five compact columns, not seven nowrap ones: the
                            customer sits under the job order number, stage
                            and payment share a cell, the description wraps
                            and the due time drops to a second line. As seven
                            columns the table was wider than its card below
                            1536px and Start / Done scrolled out of view.
                        -->
                        <TableCell>
                            <div class="flex flex-col gap-0.5">
                                <div class="flex items-center gap-2">
                                    <span class="font-medium tabular-nums">
                                        {{ jobOrder.number ?? '—' }}
                                    </span>
                                    <Badge
                                        v-if="jobOrder.is_rush"
                                        variant="outline"
                                        class="border-brand/40 text-brand"
                                    >
                                        <Zap class="size-3" />
                                        Rush
                                    </Badge>
                                </div>
                                <span class="text-muted-foreground text-sm">
                                    {{ jobOrder.queue_entry?.customer?.name }}
                                </span>
                            </div>
                        </TableCell>
                        <TableCell class="min-w-32 whitespace-normal">
                            {{ jobOrder.description }}
                        </TableCell>
                        <TableCell>
                            <div class="flex flex-col items-start gap-1">
                                <Badge
                                    :variant="
                                        jobOrder.status === 'for_production'
                                            ? 'outline'
                                            : 'secondary'
                                    "
                                    :class="{
                                        'text-green-600 dark:text-green-400':
                                            jobOrder.status ===
                                            'ready_for_pickup',
                                    }"
                                >
                                    {{
                                        STAGE_LABELS[jobOrder.status] ??
                                        jobOrder.status
                                    }}
                                </Badge>
                                <Badge
                                    :variant="
                                        jobOrder.cleared_for_production
                                            ? 'secondary'
                                            : 'outline'
                                    "
                                    :data-test="`payment-badge-${jobOrder.id}`"
                                >
                                    {{
                                        paymentStatusLabel(
                                            jobOrder.payment_status,
                                        )
                                    }}
                                </Badge>
                            </div>
                        </TableCell>
                        <TableCell>
                            <div class="flex flex-col tabular-nums">
                                <span>{{ dueDay(jobOrder.due_at) }}</span>
                                <span
                                    v-if="jobOrder.due_at"
                                    class="text-muted-foreground text-sm"
                                >
                                    {{ dueTimeOnly(jobOrder.due_at) }}
                                </span>
                                <span
                                    v-if="isOverdue(jobOrder.due_at)"
                                    class="text-sm text-amber-600 dark:text-amber-400"
                                >
                                    Overdue
                                </span>
                            </div>
                        </TableCell>
                        <TableCell class="text-right">
                            <!--
                                A finished order is never "awaiting payment"
                                here: the counter's release gate collects the
                                balance, and Undo must stay reachable.
                            -->
                            <p
                                v-if="
                                    !jobOrder.cleared_for_production &&
                                    jobOrder.status !== 'ready_for_pickup'
                                "
                                class="text-muted-foreground text-sm"
                                :data-test="`awaiting-payment-${jobOrder.id}`"
                            >
                                Awaiting payment
                            </p>
                            <div
                                v-else
                                class="flex items-center justify-end gap-2"
                            >
                                <Form
                                    v-if="jobOrder.status === 'for_production'"
                                    v-bind="
                                        ProductionStageController.start.form(
                                            jobOrder.id,
                                        )
                                    "
                                    :options="{ preserveScroll: true }"
                                    v-slot="{ processing }"
                                >
                                    <Button
                                        type="submit"
                                        size="sm"
                                        variant="outline"
                                        :disabled="processing"
                                        :data-test="`start-job-order-${jobOrder.id}-button`"
                                    >
                                        <Spinner v-if="processing" />
                                        Start
                                    </Button>
                                </Form>

                                <Form
                                    v-if="
                                        jobOrder.status !== 'ready_for_pickup'
                                    "
                                    v-bind="
                                        ProductionStageController.done.form(
                                            jobOrder.id,
                                        )
                                    "
                                    :options="{ preserveScroll: true }"
                                    v-slot="{ processing }"
                                >
                                    <Button
                                        type="submit"
                                        size="sm"
                                        :disabled="processing"
                                        :data-test="`done-job-order-${jobOrder.id}-button`"
                                    >
                                        <Spinner v-if="processing" />
                                        Done
                                    </Button>
                                </Form>

                                <Form
                                    v-if="jobOrder.status !== 'for_production'"
                                    v-bind="
                                        ProductionStageController.undo.form(
                                            jobOrder.id,
                                        )
                                    "
                                    :options="{ preserveScroll: true }"
                                    v-slot="{ processing }"
                                >
                                    <Button
                                        type="submit"
                                        size="sm"
                                        variant="ghost"
                                        :disabled="processing"
                                        :data-test="`undo-job-order-${jobOrder.id}-button`"
                                    >
                                        Undo
                                    </Button>
                                </Form>
                            </div>
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </DataTableCard>
    </PageContainer>
</template>

<script setup lang="ts">
import { Form, Head, router, usePoll } from '@inertiajs/vue3';
import { Search, Zap } from '@lucide/vue';
import { ref, watch } from 'vue';
import JobOrderReleaseController from '@/actions/App/Http/Controllers/FrontlineStaff/JobOrderReleaseController';
import JobOrderTotal from '@/components/JobOrderTotal.vue';
import DataTableCard from '@/components/DataTableCard.vue';
import EmptyState from '@/components/EmptyState.vue';
import PageContainer from '@/components/PageContainer.vue';
import PageHeader from '@/components/PageHeader.vue';
import SectionHeading from '@/components/SectionHeading.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
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
import { frontlineStaffNavItems } from '@/config/nav/frontline-staff';
import {
    balanceLabel,
    jobOrderStatusBadge,
    money,
    paymentStatusBadge,
    paymentStatusLabel,
} from '@/lib/jobOrders';
import { dashboard } from '@/routes/frontline-staff';
import { show as jobOrderShow } from '@/routes/frontline-staff/job-orders';

interface ReadyForPickupJobOrder {
    id: number;
    number: string | null;
    description: string;
    payment_status: string;
    queue_entry_id: number;
    updated_at: string;
    // The production_logs row that recorded the ready_for_pickup
    // transition. Null only for job orders that reached the stage without
    // a logged transition (legacy/seeded rows), which fall back to
    // updated_at.
    ready_at: string | null;
    queue_entry: { customer: { name: string } };
    total_amount: number | null;
    display_total: number | null;
    amount_paid: number | null;
    is_rush: boolean;
}

interface JobOrderSearchResult {
    id: number;
    number: string | null;
    description: string;
    status: string;
    display_status: string;
    payment_status: string;
    is_rush: boolean;
    created_at: string;
    queue_entry: { customer: { name: string } | null } | null;
    assigned_artist: { name: string; artist_label: string | null } | null;
    total_amount: number | null;
    display_total: number | null;
    amount_paid: number | null;
}

const props = defineProps<{
    readyForPickup: ReadyForPickupJobOrder[];
    searchResults: JobOrderSearchResult[];
    filters: { q?: string };
}>();

defineOptions({
    layout: {
        navItems: frontlineStaffNavItems,
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: dashboard(),
            },
        ],
    },
});

// D-13/D-14: a derived, polled query — nothing stored. Matches
// QueueDisplay.vue's exact usePoll call shape. Scoped to `readyForPickup`
// so a five-second poll never wipes out search results mid-typing.
usePoll(5000, { only: ['readyForPickup'] });

const searchTerm = ref(props.filters.q ?? '');
const searching = ref(false);
let searchTimer: ReturnType<typeof setTimeout> | undefined;

// Debounced so a staff member typing a job order number fires one request
// instead of fourteen.
watch(searchTerm, (term) => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        router.get(
            dashboard.url(),
            term.trim() === '' ? {} : { q: term.trim() },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                only: ['searchResults', 'filters'],
                onStart: () => (searching.value = true),
                onFinish: () => (searching.value = false),
            },
        );
    }, 300);
});

function clearSearch(): void {
    searchTerm.value = '';
}

const JOB_ORDER_STAGE_LABELS: Record<string, string> = {
    intake: 'Intake',
    validation_failed: 'Needs a New File',
    assigned: 'With an Artist',
    in_consultation: 'In Consultation',
    in_design: 'In Design',
    pending_review: 'Awaiting Approval',
    design_approved: 'Design Approved',
    ready_for_production: 'Ready for Production',
    for_production: 'For Production',
    printing: 'Printing',
    ready_for_pickup: 'Ready for Pickup',
    released: 'Released',
    cancelled: 'Cancelled',
};

function stageLabel(result: JobOrderSearchResult): string {
    return (
        JOB_ORDER_STAGE_LABELS[result.display_status] ?? result.display_status
    );
}

function whereToSend(result: JobOrderSearchResult): string {
    if (result.display_status === 'cancelled') {
        return 'This order was cancelled.';
    }

    if (result.display_status === 'released') {
        return 'Already collected by the customer.';
    }

    if (result.display_status === 'ready_for_pickup') {
        return 'On the shelf — ready to hand over.';
    }

    if (result.assigned_artist) {
        return `With ${result.assigned_artist.artist_label ?? result.assigned_artist.name}.`;
    }

    return 'Still in progress.';
}

// Mirrors JobOrderReleaseController::store's own server-side gate (POS-09) —
// this only decides whether to render the button; the controller re-checks
// payment_status independently and rejects a direct request regardless of
// what this predicate returns. released_at is intentionally not part of
// this predicate (unlike QueueList.vue's version): the backend query behind
// this page's readyForPickup prop already filters whereNull('released_at'),
// so every row here is structurally un-released.
function isReleaseEligible(jobOrder: ReadyForPickupJobOrder): boolean {
    return (
        jobOrder.payment_status === 'paid' ||
        jobOrder.payment_status === 'on_credit'
    );
}

/**
 * Open the full record for a job order. Every row on this page is a lookup
 * result, so the row itself is the target — the counter is reading, not
 * choosing between actions.
 */
function openJobOrder(id: number): void {
    router.visit(jobOrderShow.url(id));
}

/**
 * "Ready Since" cell copy (Copywriting Contract): "12 minutes ago" /
 * "2 hours ago" / "Yesterday, 4:30 PM". Measured from ready_at (the
 * logged ready_for_pickup transition), never from updated_at, which any
 * unrelated write to the job order resets.
 */
function timeAgo(isoString: string): string {
    const then = new Date(isoString);
    const now = new Date();
    const diffMinutes = Math.max(
        0,
        Math.floor((now.getTime() - then.getTime()) / 60000),
    );

    if (diffMinutes < 1) {
        return 'Just now';
    }

    if (diffMinutes < 60) {
        return `${diffMinutes} minute${diffMinutes === 1 ? '' : 's'} ago`;
    }

    if (then.toDateString() === now.toDateString()) {
        const diffHours = Math.floor(diffMinutes / 60);

        return `${diffHours} hour${diffHours === 1 ? '' : 's'} ago`;
    }

    const time = then.toLocaleTimeString('en-PH', {
        hour: 'numeric',
        minute: '2-digit',
    });

    const yesterday = new Date(now);
    yesterday.setDate(yesterday.getDate() - 1);

    if (then.toDateString() === yesterday.toDateString()) {
        return `Yesterday, ${time}`;
    }

    const date = then.toLocaleDateString('en-PH', {
        month: 'short',
        day: 'numeric',
    });

    return `${date}, ${time}`;
}
</script>

<template>
    <Head title="Frontline Dashboard" />

    <PageContainer>
        <PageHeader
            title="Frontline Dashboard"
            description="What needs a customer-facing action right now."
        />

        <SectionHeading
            title="Find a Job Order"
            description="Search by job order number, description, or customer name — at any stage, not just the ones ready for pickup."
        />

        <DataTableCard>
            <div class="flex flex-col gap-4 p-4">
                <div class="grid gap-2">
                    <Label for="job-order-search">Search job orders</Label>
                    <div class="flex items-center gap-2">
                        <div class="relative flex-1">
                            <Search
                                class="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2"
                            />
                            <Input
                                id="job-order-search"
                                v-model="searchTerm"
                                type="search"
                                class="pl-9"
                                placeholder="JO-2026-1234, tarpaulin, or Maria Santos"
                                autocomplete="off"
                            />
                        </div>
                        <Button
                            v-if="searchTerm !== ''"
                            type="button"
                            variant="outline"
                            @click="clearSearch"
                        >
                            Clear
                        </Button>
                    </div>
                </div>

                <p
                    v-if="searchTerm.trim() === ''"
                    class="text-muted-foreground text-sm"
                >
                    Start typing to look up an order the customer is asking
                    about.
                </p>
                <p
                    v-else-if="searching"
                    class="text-muted-foreground text-sm"
                    aria-live="polite"
                >
                    Searching…
                </p>
                <EmptyState
                    v-else-if="searchResults.length === 0"
                    title="No job order matches that"
                    description="Check the spelling, or try just the customer's surname or part of the description."
                />
            </div>

            <div
                v-if="searchResults.length > 0 && !searching"
                class="w-full overflow-x-auto"
            >
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Job Order</TableHead>
                            <TableHead>Customer</TableHead>
                            <TableHead>Description</TableHead>
                            <TableHead>Stage</TableHead>
                            <TableHead>Payment</TableHead>
                            <TableHead class="text-right">Total</TableHead>
                            <TableHead class="text-right">Balance</TableHead>
                            <TableHead>Where to send them</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow
                            v-for="result in searchResults"
                            :key="result.id"
                            class="hover:bg-accent/50 cursor-pointer"
                            tabindex="0"
                            :data-test="`search-result-${result.id}-row`"
                            @click="openJobOrder(result.id)"
                            @keyup.enter="openJobOrder(result.id)"
                        >
                            <TableCell class="font-medium tabular-nums">
                                <div class="flex items-center gap-2">
                                    {{ result.number ?? '—' }}
                                    <Badge
                                        v-if="result.is_rush"
                                        variant="outline"
                                        class="border-brand/40 text-brand"
                                    >
                                        <Zap class="size-3" />
                                        Rush
                                    </Badge>
                                </div>
                            </TableCell>
                            <TableCell>
                                {{ result.queue_entry?.customer?.name ?? '—' }}
                            </TableCell>
                            <TableCell>{{ result.description }}</TableCell>
                            <TableCell>
                                <StatusBadge
                                    :tone="
                                        jobOrderStatusBadge(
                                            result.display_status,
                                        )
                                    "
                                >
                                    {{ stageLabel(result) }}
                                </StatusBadge>
                            </TableCell>
                            <TableCell>
                                <StatusBadge
                                    :tone="
                                        paymentStatusBadge(
                                            result.payment_status,
                                        )
                                    "
                                >
                                    {{
                                        paymentStatusLabel(
                                            result.payment_status,
                                        )
                                    }}
                                </StatusBadge>
                            </TableCell>
                            <TableCell class="text-right tabular-nums">
                                <JobOrderTotal :job-order="result" />
                            </TableCell>
                            <TableCell class="text-right tabular-nums">
                                {{ balanceLabel(result) }}
                            </TableCell>
                            <TableCell class="text-muted-foreground">
                                {{ whereToSend(result) }}
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>
        </DataTableCard>

        <SectionHeading
            title="Ready for Pickup"
            description="Job orders waiting on the shelf for the customer to collect."
        />

        <DataTableCard>
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Job Order</TableHead>
                        <TableHead>Customer</TableHead>
                        <TableHead>Description</TableHead>
                        <TableHead>Ready Since</TableHead>
                        <TableHead>Payment</TableHead>
                        <TableHead class="text-right">Total</TableHead>
                        <TableHead class="text-right">Balance</TableHead>
                        <TableHead class="text-right">Actions</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableEmpty v-if="readyForPickup.length === 0" :colspan="8">
                        <EmptyState
                            title="Nothing ready for pickup"
                            description="Job orders appear here the moment Production marks them Ready for Pickup."
                        />
                    </TableEmpty>
                    <TableRow
                        v-for="jobOrder in readyForPickup"
                        v-else
                        :key="jobOrder.id"
                        class="hover:bg-accent/50 cursor-pointer"
                        tabindex="0"
                        :data-test="`ready-for-pickup-${jobOrder.id}-row`"
                        @click="openJobOrder(jobOrder.id)"
                        @keyup.enter="openJobOrder(jobOrder.id)"
                    >
                        <TableCell>
                            <span class="text-muted-foreground tabular-nums">
                                {{ jobOrder.number ?? '—' }}
                            </span>
                        </TableCell>
                        <TableCell>
                            {{ jobOrder.queue_entry.customer.name }}
                        </TableCell>
                        <TableCell>{{ jobOrder.description }}</TableCell>
                        <TableCell>
                            {{
                                timeAgo(
                                    jobOrder.ready_at ?? jobOrder.updated_at,
                                )
                            }}
                        </TableCell>
                        <TableCell>
                            <StatusBadge
                                :tone="
                                    paymentStatusBadge(jobOrder.payment_status)
                                "
                            >
                                {{
                                    paymentStatusLabel(jobOrder.payment_status)
                                }}
                            </StatusBadge>
                        </TableCell>
                        <TableCell class="text-right tabular-nums">
                            <JobOrderTotal :job-order="jobOrder" />
                        </TableCell>
                        <TableCell class="text-right tabular-nums">
                            {{ balanceLabel(jobOrder) }}
                        </TableCell>
                        <TableCell class="text-right" @click.stop>
                            <Form
                                v-if="isReleaseEligible(jobOrder)"
                                v-bind="
                                    JobOrderReleaseController.store.form(
                                        jobOrder.id,
                                    )
                                "
                                :options="{ preserveScroll: true }"
                                v-slot="{ processing }"
                            >
                                <Button
                                    type="submit"
                                    :disabled="processing"
                                    :data-test="`release-job-order-${jobOrder.id}-button`"
                                >
                                    Release to Customer
                                </Button>
                            </Form>
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </DataTableCard>
    </PageContainer>
</template>

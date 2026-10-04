<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { Inbox, LayoutList, Zap } from '@lucide/vue';
import { computed } from 'vue';
import JobOrderQueueController from '@/actions/App/Http/Controllers/Artist/JobOrderQueueController';
import SessionStatusController from '@/actions/App/Http/Controllers/Artist/SessionStatusController';
import DataTableCard from '@/components/DataTableCard.vue';
import EmptyState from '@/components/EmptyState.vue';
import PageContainer from '@/components/PageContainer.vue';
import PageHeader from '@/components/PageHeader.vue';
import SectionHeading from '@/components/SectionHeading.vue';
import StatCard from '@/components/StatCard.vue';
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
import StatusBadge from '@/components/StatusBadge.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
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
import { useBusinessTime } from '@/composables/useBusinessTime';
import { artistNavItems } from '@/config/nav/artist';
import { artistStatusBadge, artistStatusLabel } from '@/lib/roles';
import { jobOrderStatusBadge } from '@/lib/jobOrders';
import { dashboard } from '@/routes/artist';
import { show } from '@/routes/artist/job-orders';

interface ArtistJobOrder {
    id: number;
    number: string | null;
    description: string;
    status: string;
    is_rush: boolean;
    type: string;
    deadline: string | null;
}

interface PoolJobOrder {
    id: number;
    number: string;
    description: string;
    created_at: string;
    is_rush: boolean;
    type: string;
    deadline: string | null;
    queue_entry: { customer: { name: string } | null } | null;
}

const props = defineProps<{
    jobOrders: ArtistJobOrder[];
    availableJobOrders: PoolJobOrder[];
    artistStatus: string;
    artistLabel: string | null;
}>();

// New work reaches the shared pool, and a client's verdict reaches this
// Artist's own queue, without a reload.
useLivePoll(['jobOrders', 'availableJobOrders', 'artistStatus']);

/**
 * On break or off shift, the queue is read-only.
 *
 * Mirrored by a server-side guard on next/forward -- greying a button out
 * does not close the route behind it.
 */
const isOnShift = computed(() => props.artistStatus === 'available');

const offDutyReason = computed(() =>
    props.artistStatus === 'on_break'
        ? 'You are on break. End your break to work your queue.'
        : 'Your shift has ended. Start your shift to work your queue.',
);

/**
 * Type A arrived print-ready and only reached an artist because its file
 * needs fixing; Type B is a consultation from the start. Which one a row is
 * changes what the artist is expected to do with it, so it belongs in the
 * table rather than one level in.
 */
function typeLabel(type: string): string {
    return type === 'type_a' ? 'Type A' : 'Type B';
}

/** Matches the 'en-PH' long-date convention used across the other portals. */
const { formatDay } = useBusinessTime();

function deadlineLabel(deadline: string | null): string {
    if (deadline === null) {
        return 'No deadline';
    }

    return formatDay(deadline, {
        month: 'long',
        day: 'numeric',
        year: 'numeric',
    });
}

const rushCount = computed(
    () =>
        props.jobOrders.filter((jobOrder) => jobOrder.is_rush).length +
        props.availableJobOrders.filter((jobOrder) => jobOrder.is_rush).length,
);

defineOptions({
    layout: {
        navItems: artistNavItems,
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: dashboard(),
            },
        ],
    },
});

// A job order the Artist might still observe at any of these statuses has
// already been approved and has left (or is leaving) the Artist's hands —
// 06-04's automatic EnterProduction wiring means design_approved itself is
// now transient on the real approve() path, immediately followed by one of
// the three production statuses in the same transaction.
const POST_APPROVAL_STATUSES = [
    'design_approved',
    'for_production',
    'printing',
    'ready_for_pickup',
];

function statusLabel(status: string): string {
    if (status === 'in_consultation') {
        return 'In Consultation';
    }

    if (status === 'in_design') {
        return 'In Design';
    }

    if (status === 'pending_review') {
        return 'Pending Review';
    }

    if (status === 'design_approved') {
        return 'Design Approved';
    }

    if (status === 'for_production') {
        return 'For Production';
    }

    if (status === 'printing') {
        return 'Printing';
    }

    if (status === 'ready_for_pickup') {
        return 'Ready for Pickup';
    }

    return 'Assigned';
}

function waitingSince(createdAt: string): string {
    const minutes = Math.max(
        0,
        Math.round((Date.now() - new Date(createdAt).getTime()) / 60000),
    );

    if (minutes < 1) {
        return 'Just now';
    }

    if (minutes < 60) {
        return `${minutes}m waiting`;
    }

    return `${Math.floor(minutes / 60)}h ${minutes % 60}m waiting`;
}
</script>

<template>
    <Head title="Artist Dashboard" />

    <PageContainer>
        <PageHeader
            title="Artist Dashboard"
            description="Claim work from the shared pool, then work your own queue top to bottom."
        />

        <Card>
            <CardContent
                class="flex flex-wrap items-center justify-between gap-4"
            >
                <div class="flex flex-wrap items-center gap-2">
                    <Badge
                        v-if="artistLabel"
                        variant="secondary"
                        data-test="artist-label-badge"
                    >
                        {{ artistLabel }}
                    </Badge>
                    <StatusBadge :tone="artistStatusBadge(artistStatus)">
                        {{ artistStatusLabel(artistStatus) }}
                    </StatusBadge>
                </div>
                <div class="flex items-center gap-2">
                    <Form
                        v-if="artistStatus === 'off_shift'"
                        v-bind="SessionStatusController.startShift.form()"
                        :options="{ preserveScroll: true }"
                        v-slot="{ processing }"
                    >
                        <Button
                            type="submit"
                            :disabled="processing"
                            data-test="start-shift-button"
                        >
                            Start Shift
                        </Button>
                    </Form>
                    <Form
                        v-if="artistStatus === 'available'"
                        v-bind="SessionStatusController.startBreak.form()"
                        :options="{ preserveScroll: true }"
                        v-slot="{ processing }"
                    >
                        <Button
                            type="submit"
                            :disabled="processing"
                            data-test="start-break-button"
                        >
                            Start Break
                        </Button>
                    </Form>
                    <Form
                        v-if="artistStatus === 'on_break'"
                        v-bind="SessionStatusController.endBreak.form()"
                        :options="{ preserveScroll: true }"
                        v-slot="{ processing }"
                    >
                        <Button
                            type="submit"
                            :disabled="processing"
                            data-test="end-break-button"
                        >
                            End Break
                        </Button>
                    </Form>
                    <Form
                        v-if="
                            artistStatus === 'available' ||
                            artistStatus === 'on_break'
                        "
                        v-bind="SessionStatusController.endShift.form()"
                        :options="{ preserveScroll: true }"
                        v-slot="{ processing }"
                    >
                        <Button
                            type="submit"
                            variant="outline"
                            :disabled="processing"
                            data-test="end-shift-button"
                        >
                            End Shift
                        </Button>
                    </Form>
                </div>
            </CardContent>
        </Card>

        <div class="grid grid-cols-1 gap-4 @lg:grid-cols-2 @3xl:grid-cols-3">
            <StatCard
                label="In your queue"
                :value="jobOrders.length"
                hint="Jobs you have accepted"
                :icon="LayoutList"
            />
            <StatCard
                label="Waiting to be claimed"
                :value="availableJobOrders.length"
                hint="First to accept gets the job"
                :icon="Inbox"
                ink="magenta"
            />
            <StatCard
                label="Rush"
                :value="rushCount"
                hint="Priority jobs across both lists"
                :icon="Zap"
                :tone="rushCount > 0 ? 'attention' : 'default'"
            />
        </div>

        <section class="flex flex-col gap-3">
            <SectionHeading
                title="Available Jobs"
                description="Unclaimed jobs, rush first. Accepting one moves it into your queue."
            />

            <DataTableCard>
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Job Order</TableHead>
                            <TableHead>Type</TableHead>
                            <TableHead>Customer</TableHead>
                            <TableHead>Waiting</TableHead>
                            <TableHead class="text-right">Actions</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableEmpty
                            v-if="availableJobOrders.length === 0"
                            :colspan="5"
                        >
                            <EmptyState
                                title="No jobs waiting to be accepted"
                                description="New jobs appear here the moment Frontline Staff create them."
                                :icon="Inbox"
                            />
                        </TableEmpty>
                        <TableRow
                            v-for="jobOrder in availableJobOrders"
                            v-else
                            :key="jobOrder.id"
                            :class="{
                                'bg-warning/10 hover:bg-warning/15':
                                    jobOrder.is_rush,
                            }"
                        >
                            <TableCell>
                                <div class="flex flex-col gap-1">
                                    <div
                                        class="flex flex-wrap items-center gap-2"
                                    >
                                        <span class="font-medium tabular-nums">
                                            {{ jobOrder.number }}
                                        </span>
                                        <Badge
                                            v-if="jobOrder.is_rush"
                                            variant="outline"
                                            class="border-brand/40 text-brand"
                                            :data-test="`pool-rush-${jobOrder.id}-badge`"
                                        >
                                            <Zap class="size-3" />
                                            Rush
                                        </Badge>
                                    </div>
                                    <span class="text-muted-foreground text-sm">
                                        {{ jobOrder.description }}
                                    </span>
                                </div>
                            </TableCell>
                            <TableCell>
                                <Badge
                                    variant="outline"
                                    :data-test="`pool-type-${jobOrder.id}-badge`"
                                >
                                    {{ typeLabel(jobOrder.type) }}
                                </Badge>
                            </TableCell>
                            <TableCell>
                                {{
                                    jobOrder.queue_entry?.customer?.name ?? '—'
                                }}
                            </TableCell>
                            <TableCell class="text-muted-foreground">
                                {{ waitingSince(jobOrder.created_at) }}
                            </TableCell>
                            <TableCell>
                                <div class="flex items-center justify-end">
                                    <!--
                                        Accepting is first-come and moves the
                                        job into this artist's queue, so it
                                        asks first rather than claiming on a
                                        stray click.
                                    -->
                                    <AlertDialog>
                                        <AlertDialogTrigger as-child>
                                            <Button
                                                type="button"
                                                :disabled="
                                                    artistStatus !== 'available'
                                                "
                                                :data-test="`accept-${jobOrder.id}-button`"
                                            >
                                                Accept
                                            </Button>
                                        </AlertDialogTrigger>
                                        <AlertDialogContent>
                                            <AlertDialogHeader>
                                                <AlertDialogTitle>
                                                    Accept this job order?
                                                </AlertDialogTitle>
                                                <AlertDialogDescription>
                                                    {{ jobOrder.number }} —
                                                    {{ jobOrder.description }}
                                                    for
                                                    {{
                                                        jobOrder.queue_entry
                                                            ?.customer?.name ??
                                                        'a walk-in customer'
                                                    }}
                                                    moves into your queue and
                                                    off the shared list.
                                                </AlertDialogDescription>
                                            </AlertDialogHeader>
                                            <AlertDialogFooter>
                                                <AlertDialogCancel>
                                                    Cancel
                                                </AlertDialogCancel>
                                                <Form
                                                    v-bind="
                                                        JobOrderQueueController.accept.form(
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
                                                        class="w-full"
                                                        :disabled="processing"
                                                        :data-test="`confirm-accept-${jobOrder.id}-button`"
                                                    >
                                                        Accept Job Order
                                                    </Button>
                                                </Form>
                                            </AlertDialogFooter>
                                        </AlertDialogContent>
                                    </AlertDialog>
                                </div>
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </DataTableCard>
        </section>

        <section class="flex flex-col gap-3">
            <div class="flex flex-wrap items-end justify-between gap-2">
                <SectionHeading
                    title="My Queue"
                    description="Your accepted jobs, rush first and newest first within each priority."
                />
                <p
                    v-if="!isOnShift"
                    class="text-muted-foreground text-sm"
                    data-test="queue-off-duty-note"
                >
                    {{ offDutyReason }}
                </p>
            </div>
            <DataTableCard>
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Job Order</TableHead>
                            <TableHead>Type</TableHead>
                            <TableHead>Status</TableHead>
                            <TableHead>Deadline</TableHead>
                            <TableHead class="text-right">Actions</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableEmpty v-if="jobOrders.length === 0" :colspan="5">
                            <EmptyState
                                title="No job orders assigned"
                                description="Accept a job from Available Jobs above to start working on it."
                                :icon="LayoutList"
                            />
                        </TableEmpty>
                        <TableRow
                            v-for="jobOrder in jobOrders"
                            v-else
                            :key="jobOrder.id"
                            :class="{
                                'bg-warning/10 hover:bg-warning/15':
                                    jobOrder.is_rush,
                            }"
                        >
                            <TableCell>
                                <div class="flex flex-col gap-1">
                                    <div
                                        class="flex flex-wrap items-center gap-2"
                                    >
                                        <span class="font-medium tabular-nums">
                                            {{ jobOrder.number ?? '—' }}
                                        </span>
                                        <Badge
                                            v-if="jobOrder.is_rush"
                                            variant="outline"
                                            class="border-brand/40 text-brand"
                                            :data-test="`queue-rush-${jobOrder.id}-badge`"
                                        >
                                            <Zap class="size-3" />
                                            Rush
                                        </Badge>
                                    </div>
                                    <span class="text-muted-foreground text-sm">
                                        {{ jobOrder.description }}
                                    </span>
                                </div>
                            </TableCell>
                            <TableCell>
                                <Badge
                                    variant="outline"
                                    :data-test="`queue-type-${jobOrder.id}-badge`"
                                >
                                    {{ typeLabel(jobOrder.type) }}
                                </Badge>
                            </TableCell>
                            <TableCell>
                                <div class="flex flex-wrap items-center gap-2">
                                    <StatusBadge
                                        :tone="
                                            jobOrderStatusBadge(jobOrder.status)
                                        "
                                    >
                                        {{ statusLabel(jobOrder.status) }}
                                    </StatusBadge>
                                </div>
                            </TableCell>
                            <TableCell class="text-muted-foreground">
                                {{ deadlineLabel(jobOrder.deadline) }}
                            </TableCell>
                            <TableCell>
                                <div
                                    class="flex items-center justify-end gap-2"
                                >
                                    <template
                                        v-if="jobOrder.status === 'assigned'"
                                    >
                                        <Form
                                            v-bind="
                                                JobOrderQueueController.next.form(
                                                    jobOrder.id,
                                                )
                                            "
                                            :options="{ preserveScroll: true }"
                                            v-slot="{ processing }"
                                        >
                                            <Button
                                                type="submit"
                                                :disabled="
                                                    processing || !isOnShift
                                                "
                                                :data-test="`next-${jobOrder.id}-button`"
                                            >
                                                Next
                                            </Button>
                                        </Form>
                                        <Form
                                            v-bind="
                                                JobOrderQueueController.forward.form(
                                                    jobOrder.id,
                                                )
                                            "
                                            :options="{ preserveScroll: true }"
                                            v-slot="{ processing }"
                                        >
                                            <Button
                                                type="submit"
                                                variant="outline"
                                                :disabled="
                                                    processing || !isOnShift
                                                "
                                                :data-test="`forward-${jobOrder.id}-button`"
                                            >
                                                Forward
                                            </Button>
                                        </Form>
                                    </template>
                                    <template
                                        v-else-if="
                                            jobOrder.status ===
                                                'in_consultation' ||
                                            jobOrder.status === 'in_design'
                                        "
                                    >
                                        <Form
                                            v-bind="
                                                JobOrderQueueController.forward.form(
                                                    jobOrder.id,
                                                )
                                            "
                                            :options="{ preserveScroll: true }"
                                            v-slot="{ processing }"
                                        >
                                            <Button
                                                type="submit"
                                                variant="outline"
                                                :disabled="
                                                    processing || !isOnShift
                                                "
                                                :data-test="`forward-${jobOrder.id}-button`"
                                            >
                                                Forward
                                            </Button>
                                        </Form>
                                        <Button v-if="isOnShift" as-child>
                                            <Link
                                                :href="show(jobOrder.id).url"
                                                :data-test="`continue-${jobOrder.id}-link`"
                                            >
                                                Continue
                                            </Link>
                                        </Button>
                                    </template>
                                    <Button
                                        v-else-if="
                                            jobOrder.status ===
                                                'pending_review' && isOnShift
                                        "
                                        as-child
                                    >
                                        <Link
                                            :href="show(jobOrder.id).url"
                                            :data-test="`continue-${jobOrder.id}-link`"
                                        >
                                            Continue
                                        </Link>
                                    </Button>
                                    <Button
                                        v-else-if="
                                            POST_APPROVAL_STATUSES.includes(
                                                jobOrder.status,
                                            )
                                        "
                                        as-child
                                        variant="outline"
                                    >
                                        <Link
                                            :href="show(jobOrder.id).url"
                                            :data-test="`view-${jobOrder.id}-link`"
                                        >
                                            View
                                        </Link>
                                    </Button>
                                </div>
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </DataTableCard>
        </section>
    </PageContainer>
</template>

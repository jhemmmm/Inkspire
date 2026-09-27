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
import { artistNavItems } from '@/config/nav/artist';
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
function deadlineLabel(deadline: string | null): string {
    if (deadline === null) {
        return 'No deadline';
    }

    return new Date(deadline).toLocaleDateString('en-PH', {
        month: 'long',
        day: 'numeric',
        year: 'numeric',
    });
}

/**
 * Rush and regular are shown as two tables rather than one sorted list.
 * Sorting alone puts the boundary between "drop everything" and "normal
 * work" somewhere in the middle of a scrolling table, where it is invisible
 * — the artist has to read the badge on every row to find it.
 *
 * Both lists arrive from the server already ordered (rush first, newest
 * first within each), so partitioning preserves that order and the top row
 * of the rush table stays the row `nextEligibleId()` will authorise.
 */
function splitByRush<T extends { is_rush: boolean }>(
    rows: T[],
): { rush: T[]; regular: T[] } {
    return {
        rush: rows.filter((row) => row.is_rush),
        regular: rows.filter((row) => !row.is_rush),
    };
}

const availableGroups = computed(() => {
    const { rush, regular } = splitByRush(props.availableJobOrders);

    return [
        {
            key: 'rush',
            title: 'Available — Rush Print',
            description:
                'Priority jobs nobody has claimed yet. Take these before anything below.',
            rows: rush,
            emptyTitle: 'No rush jobs waiting',
            emptyDescription:
                'Rush jobs appear here the moment Frontline Staff create one.',
        },
        {
            key: 'regular',
            title: 'Available — Regular',
            description:
                'Unclaimed jobs — first to accept gets it. Accepting one moves it into your queue.',
            rows: regular,
            emptyTitle: 'No jobs waiting to be accepted',
            emptyDescription:
                'New jobs appear here the moment Frontline Staff create them.',
        },
    ];
});

const queueGroups = computed(() => {
    const { rush, regular } = splitByRush(props.jobOrders);

    return [
        {
            key: 'rush',
            title: 'My Queue — Rush Print',
            description: 'Your priority work, newest first.',
            rows: rush,
            emptyTitle: 'No rush jobs in your queue',
            emptyDescription:
                'Accept a rush job from Available above and it lands here.',
        },
        {
            key: 'regular',
            title: 'My Queue — Regular',
            description: 'The rest of your queue, newest first.',
            rows: regular,
            emptyTitle: 'No job orders assigned',
            emptyDescription:
                'Accept a job from Available Jobs above to start working on it.',
        },
    ];
});

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
// the four production statuses in the same transaction.
const POST_APPROVAL_STATUSES = [
    'design_approved',
    'for_production',
    'printing',
    'quality_check',
    'ready_for_pickup',
];

function statusBadgeVariant(
    status: string,
): 'default' | 'secondary' | undefined {
    if (status === 'in_consultation' || status === 'in_design') {
        return 'secondary';
    }

    if (POST_APPROVAL_STATUSES.includes(status)) {
        return undefined;
    }

    return 'default';
}

function statusBadgeClass(status: string): string {
    if (POST_APPROVAL_STATUSES.includes(status)) {
        return 'text-green-600 dark:text-green-400';
    }

    return '';
}

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

    if (status === 'quality_check') {
        return 'Quality Check';
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

function artistStatusBadgeVariant(
    artistStatus: string,
): 'secondary' | 'outline' | undefined {
    if (artistStatus === 'on_break') {
        return 'secondary';
    }

    if (artistStatus === 'off_shift') {
        return 'outline';
    }

    return undefined;
}

function artistStatusBadgeClass(artistStatus: string): string {
    if (artistStatus === 'available') {
        return 'text-green-600 dark:text-green-400';
    }

    return '';
}

function artistStatusLabel(artistStatus: string): string {
    if (artistStatus === 'available') {
        return 'Available';
    }

    if (artistStatus === 'on_break') {
        return 'On Break';
    }

    return 'Off Shift';
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
                    <Badge
                        :variant="artistStatusBadgeVariant(artistStatus)"
                        :class="artistStatusBadgeClass(artistStatus)"
                    >
                        {{ artistStatusLabel(artistStatus) }}
                    </Badge>
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

        <div class="grid gap-4 sm:grid-cols-3">
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
            />
            <StatCard
                label="Rush"
                :value="rushCount"
                hint="Priority jobs across both lists"
                :icon="Zap"
                :tone="rushCount > 0 ? 'attention' : 'default'"
            />
        </div>

        <section
            v-for="group in availableGroups"
            :key="group.key"
            class="flex flex-col gap-3"
        >
            <SectionHeading
                :title="group.title"
                :description="group.description"
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
                        <TableEmpty v-if="group.rows.length === 0" :colspan="5">
                            <EmptyState
                                :title="group.emptyTitle"
                                :description="group.emptyDescription"
                                :icon="Inbox"
                            />
                        </TableEmpty>
                        <TableRow
                            v-for="jobOrder in group.rows"
                            v-else
                            :key="jobOrder.id"
                        >
                            <TableCell>
                                <div class="flex flex-col gap-1">
                                    <div
                                        class="flex flex-wrap items-center gap-2"
                                    >
                                        <span>{{ jobOrder.description }}</span>
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
                                    <span
                                        class="text-muted-foreground text-xs tabular-nums"
                                    >
                                        {{ jobOrder.number }}
                                    </span>
                                </div>
                            </TableCell>
                            <TableCell>
                                <Badge
                                    variant="secondary"
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
                                    <Form
                                        v-bind="
                                            JobOrderQueueController.accept.form(
                                                jobOrder.id,
                                            )
                                        "
                                        :options="{ preserveScroll: true }"
                                        v-slot="{ processing }"
                                    >
                                        <Button
                                            type="submit"
                                            :disabled="
                                                processing ||
                                                artistStatus !== 'available'
                                            "
                                            :data-test="`accept-${jobOrder.id}-button`"
                                        >
                                            Accept
                                        </Button>
                                    </Form>
                                </div>
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </DataTableCard>
        </section>

        <section
            v-for="group in queueGroups"
            :key="group.key"
            class="flex flex-col gap-3"
        >
            <div class="flex flex-wrap items-end justify-between gap-2">
                <SectionHeading
                    :title="group.title"
                    :description="group.description"
                />
                <p
                    v-if="!isOnShift && group.key === 'rush'"
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
                        <TableEmpty v-if="group.rows.length === 0" :colspan="5">
                            <EmptyState
                                :title="group.emptyTitle"
                                :description="group.emptyDescription"
                                :icon="LayoutList"
                            />
                        </TableEmpty>
                        <TableRow
                            v-for="jobOrder in group.rows"
                            v-else
                            :key="jobOrder.id"
                        >
                            <TableCell>
                                <div class="flex flex-col gap-1">
                                    <div
                                        class="flex flex-wrap items-center gap-2"
                                    >
                                        <span>{{ jobOrder.description }}</span>
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
                                    <span
                                        class="text-muted-foreground text-xs tabular-nums"
                                    >
                                        {{ jobOrder.number ?? '—' }}
                                    </span>
                                </div>
                            </TableCell>
                            <TableCell>
                                <Badge
                                    variant="secondary"
                                    :data-test="`queue-type-${jobOrder.id}-badge`"
                                >
                                    {{ typeLabel(jobOrder.type) }}
                                </Badge>
                            </TableCell>
                            <TableCell>
                                <div class="flex flex-wrap items-center gap-2">
                                    <Badge
                                        :variant="
                                            statusBadgeVariant(jobOrder.status)
                                        "
                                        :class="
                                            statusBadgeClass(jobOrder.status)
                                        "
                                    >
                                        {{ statusLabel(jobOrder.status) }}
                                    </Badge>
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
                                        <Link
                                            v-if="isOnShift"
                                            :href="show(jobOrder.id).url"
                                            :data-test="`continue-${jobOrder.id}-link`"
                                        >
                                            <Button
                                                type="button"
                                                variant="ghost"
                                            >
                                                Continue
                                            </Button>
                                        </Link>
                                    </template>
                                    <Link
                                        v-else-if="
                                            jobOrder.status ===
                                                'pending_review' && isOnShift
                                        "
                                        :href="show(jobOrder.id).url"
                                        :data-test="`continue-${jobOrder.id}-link`"
                                    >
                                        <Button type="button" variant="ghost">
                                            Continue
                                        </Button>
                                    </Link>
                                    <Link
                                        v-else-if="
                                            POST_APPROVAL_STATUSES.includes(
                                                jobOrder.status,
                                            )
                                        "
                                        :href="show(jobOrder.id).url"
                                        :data-test="`view-${jobOrder.id}-link`"
                                    >
                                        <Button type="button" variant="ghost">
                                            View
                                        </Button>
                                    </Link>
                                </div>
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </DataTableCard>
        </section>
    </PageContainer>
</template>

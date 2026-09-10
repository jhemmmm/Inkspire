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
}>();

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
                <Badge
                    :variant="artistStatusBadgeVariant(artistStatus)"
                    :class="artistStatusBadgeClass(artistStatus)"
                >
                    {{ artistStatusLabel(artistStatus) }}
                </Badge>
                <div class="flex items-center gap-2">
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

        <section class="flex flex-col gap-3">
            <SectionHeading
                title="Available Jobs"
                description="Unclaimed jobs — first to accept gets it. Accepting one moves it into your queue."
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
                                            class="border-amber-600/40 text-amber-600 dark:text-amber-400"
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

        <section class="flex flex-col gap-3">
            <SectionHeading
                title="My Queue"
                description="Jobs you have accepted, oldest first."
            />
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
                                            class="border-amber-600/40 text-amber-600 dark:text-amber-400"
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
                                                :disabled="processing"
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
                                                :disabled="processing"
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
                                                :disabled="processing"
                                                :data-test="`forward-${jobOrder.id}-button`"
                                            >
                                                Forward
                                            </Button>
                                        </Form>
                                        <Link
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
                                            jobOrder.status === 'pending_review'
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

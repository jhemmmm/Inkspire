<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { Zap } from '@lucide/vue';
import JobOrderQueueController from '@/actions/App/Http/Controllers/Artist/JobOrderQueueController';
import SessionStatusController from '@/actions/App/Http/Controllers/Artist/SessionStatusController';
import DataTableCard from '@/components/DataTableCard.vue';
import PageContainer from '@/components/PageContainer.vue';
import PageHeader from '@/components/PageHeader.vue';
import SectionHeading from '@/components/SectionHeading.vue';
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
    description: string;
    status: string;
    is_rush: boolean;
}

interface PoolJobOrder {
    id: number;
    number: string;
    description: string;
    created_at: string;
    is_rush: boolean;
    queue_entry: { customer: { name: string } | null } | null;
}

defineProps<{
    jobOrders: ArtistJobOrder[];
    availableJobOrders: PoolJobOrder[];
    artistStatus: string;
}>();

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

        <section class="flex flex-col gap-3">
            <div class="flex items-baseline justify-between gap-4">
                <SectionHeading
                    title="Available Jobs"
                    description="Unclaimed consultations. Accepting one moves it into your queue."
                />
                <p class="text-muted-foreground text-sm">
                    Unclaimed consultations — first to accept gets the job.
                </p>
            </div>

            <DataTableCard>
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Job Order</TableHead>
                            <TableHead>Customer</TableHead>
                            <TableHead>Waiting</TableHead>
                            <TableHead class="text-right">Actions</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableEmpty
                            v-if="availableJobOrders.length === 0"
                            :colspan="4"
                        >
                            <div class="flex flex-col items-center gap-1">
                                <p class="font-semibold">
                                    No jobs waiting to be accepted
                                </p>
                                <p class="text-muted-foreground">
                                    New consultations appear here the moment
                                    Frontline Staff create them.
                                </p>
                            </div>
                        </TableEmpty>
                        <TableRow
                            v-for="jobOrder in availableJobOrders"
                            v-else
                            :key="jobOrder.id"
                        >
                            <TableCell>
                                <div class="flex flex-wrap items-center gap-2">
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
                            <TableHead>Status</TableHead>
                            <TableHead class="text-right">Actions</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableEmpty v-if="jobOrders.length === 0" :colspan="3">
                            <div class="flex flex-col items-center gap-1">
                                <p class="font-semibold">
                                    No job orders assigned
                                </p>
                                <p class="text-muted-foreground">
                                    Accept a job from Available Jobs above to
                                    start working on it.
                                </p>
                            </div>
                        </TableEmpty>
                        <TableRow
                            v-for="jobOrder in jobOrders"
                            v-else
                            :key="jobOrder.id"
                        >
                            <TableCell>
                                <div class="flex flex-wrap items-center gap-2">
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

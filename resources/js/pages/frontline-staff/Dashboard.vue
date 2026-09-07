<script setup lang="ts">
import { Form, Head, usePoll } from '@inertiajs/vue3';
import JobOrderReleaseController from '@/actions/App/Http/Controllers/FrontlineStaff/JobOrderReleaseController';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
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
import { dashboard } from '@/routes/frontline-staff';

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
}

defineProps<{
    readyForPickup: ReadyForPickupJobOrder[];
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
// QueueDisplay.vue's exact usePoll call shape.
usePoll(5000, { only: ['readyForPickup'] });

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

function paymentStatusLabel(status: string): string {
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
        default:
            return status;
    }
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

    <div
        class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4"
    >
        <h1 class="text-[28px] leading-[1.2] font-semibold">
            Frontline Dashboard
        </h1>

        <div class="flex flex-col gap-1">
            <h2 class="text-[20px] leading-[1.2] font-semibold">
                Ready for Pickup
            </h2>
            <p class="text-muted-foreground">
                Job orders waiting on the shelf for the customer to collect.
            </p>
        </div>

        <div
            class="border-sidebar-border/70 dark:border-sidebar-border overflow-hidden rounded-xl border"
        >
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Job Order</TableHead>
                        <TableHead>Customer</TableHead>
                        <TableHead>Description</TableHead>
                        <TableHead>Ready Since</TableHead>
                        <TableHead>Payment</TableHead>
                        <TableHead class="text-right">Actions</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableEmpty v-if="readyForPickup.length === 0" :colspan="6">
                        <div
                            class="flex flex-col items-center gap-1 text-center"
                        >
                            <p class="font-semibold">
                                Nothing ready for pickup
                            </p>
                            <p class="text-muted-foreground">
                                Job orders appear here the moment Production
                                marks them Ready for Pickup.
                            </p>
                        </div>
                    </TableEmpty>
                    <TableRow
                        v-for="jobOrder in readyForPickup"
                        v-else
                        :key="jobOrder.id"
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
                            <Badge
                                v-if="jobOrder.payment_status === 'unpaid'"
                                variant="outline"
                            >
                                {{
                                    paymentStatusLabel(jobOrder.payment_status)
                                }}
                            </Badge>
                            <Badge
                                v-else-if="
                                    jobOrder.payment_status === 'partially_paid'
                                "
                                variant="default"
                            >
                                {{
                                    paymentStatusLabel(jobOrder.payment_status)
                                }}
                            </Badge>
                            <Badge
                                v-else-if="
                                    jobOrder.payment_status ===
                                    'pending_confirmation'
                                "
                                variant="secondary"
                            >
                                {{
                                    paymentStatusLabel(jobOrder.payment_status)
                                }}
                            </Badge>
                            <Badge
                                v-else-if="jobOrder.payment_status === 'paid'"
                                class="text-green-600 dark:text-green-400"
                            >
                                {{
                                    paymentStatusLabel(jobOrder.payment_status)
                                }}
                            </Badge>
                            <Badge
                                v-else-if="
                                    jobOrder.payment_status ===
                                    'credit_pending_approval'
                                "
                                variant="default"
                            >
                                {{
                                    paymentStatusLabel(jobOrder.payment_status)
                                }}
                            </Badge>
                            <Badge
                                v-else-if="
                                    jobOrder.payment_status === 'on_credit'
                                "
                                class="text-green-600 dark:text-green-400"
                            >
                                {{
                                    paymentStatusLabel(jobOrder.payment_status)
                                }}
                            </Badge>
                            <Badge
                                v-else-if="
                                    jobOrder.payment_status ===
                                    'credit_rejected'
                                "
                                variant="destructive"
                            >
                                {{
                                    paymentStatusLabel(jobOrder.payment_status)
                                }}
                            </Badge>
                        </TableCell>
                        <TableCell class="text-right">
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
                                    variant="outline"
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
        </div>
    </div>
</template>

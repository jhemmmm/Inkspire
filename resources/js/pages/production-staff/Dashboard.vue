<script setup lang="ts">
import { Form, Head, router, usePoll } from '@inertiajs/vue3';
import { Zap } from '@lucide/vue';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import ProductionStageController from '@/actions/App/Http/Controllers/ProductionStaff/ProductionStageController';
import AlertError from '@/components/AlertError.vue';
import InputError from '@/components/InputError.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
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
import { Textarea } from '@/components/ui/textarea';
import { productionStaffNavItems } from '@/config/nav/production-staff';
import { dashboard } from '@/routes/production-staff';

interface ProductionJobOrder {
    id: number;
    number: string | null;
    description: string;
    status: string;
    due_at: string | null;
    is_rush: boolean;
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

type FilterValue =
    | 'all'
    | 'rush'
    | 'for_production'
    | 'printing'
    | 'quality_check'
    | 'ready_for_pickup';

const stages: {
    value: 'for_production' | 'printing' | 'quality_check' | 'ready_for_pickup';
    label: string;
}[] = [
    { value: 'for_production', label: 'For Production' },
    { value: 'printing', label: 'Printing' },
    { value: 'quality_check', label: 'Quality Check' },
    { value: 'ready_for_pickup', label: 'Ready for Pickup' },
];

const activeFilter = ref<FilterValue>('all');

const rushJobOrders = computed(() =>
    props.jobOrders.filter((jobOrder) => jobOrder.is_rush),
);

const stageCounts = computed(() =>
    Object.fromEntries(
        stages.map((stage) => [
            stage.value,
            props.jobOrders.filter(
                (jobOrder) => jobOrder.status === stage.value,
            ).length,
        ]),
    ),
);

// D-14/UI-SPEC: the whole board is a handful of rows — filtering is always
// client-side, never a server round-trip.
const filteredJobOrders = computed(() => {
    if (activeFilter.value === 'all') {
        return props.jobOrders;
    }

    if (activeFilter.value === 'rush') {
        return rushJobOrders.value;
    }

    return props.jobOrders.filter(
        (jobOrder) => jobOrder.status === activeFilter.value,
    );
});

const activeFilterLabel = computed(
    () =>
        stages.find((stage) => stage.value === activeFilter.value)?.label ?? '',
);

function onTabChange(value: unknown): void {
    activeFilter.value = value as FilterValue;
}

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
 * "Today, 2:14 PM" / "Tomorrow, 9:00 AM" / a full date for anything further
 * out, per the Copywriting Contract.
 */
function dueLabel(dueAt: string | null): string {
    if (!dueAt) {
        return '—';
    }

    const date = new Date(dueAt);
    const now = new Date();
    const time = dueTimeOnly(dueAt);

    if (date.toDateString() === now.toDateString()) {
        return `Today, ${time}`;
    }

    const tomorrow = new Date(now);
    tomorrow.setDate(tomorrow.getDate() + 1);

    if (date.toDateString() === tomorrow.toDateString()) {
        return `Tomorrow, ${time}`;
    }

    const fullDate = date.toLocaleDateString('en-PH', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });

    return `${fullDate}, ${time}`;
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
 * "and {n} more" only once a third rush order exists — mirrors
 * QueueList.vue's readyForPickupBannerBody() shape.
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

// Mirrors ProductionStageController::SEQUENCE (PROD-02, D-10) — used only to
// compute the destination/previous stage label shown on each row's action
// button, never to decide what the server does.
const SEQUENCE = [
    'for_production',
    'printing',
    'quality_check',
    'ready_for_pickup',
];
const STAGE_LABELS: Record<string, string> = {
    for_production: 'For Production',
    printing: 'Printing',
    quality_check: 'Quality Check',
    ready_for_pickup: 'Ready for Pickup',
};

function nextStageLabel(status: string): string {
    const next = SEQUENCE[SEQUENCE.indexOf(status) + 1];

    return next ? STAGE_LABELS[next] : '';
}

function previousStageLabel(status: string): string {
    const previous = SEQUENCE[SEQUENCE.indexOf(status) - 1];

    return previous ? STAGE_LABELS[previous] : '';
}

/**
 * A stale-move message ("This job order already moved on...") set from the
 * server's flashed error toast (D-10/D-11 boundary rejection) — kept as a
 * page-level Alert in addition to the global toast, since a toast can be
 * dismissed or missed before it's read. Cleared on the next flash of any
 * kind (including a subsequent successful move).
 */
const staleMoveMessage = ref<string | null>(null);
let removeFlashListener: (() => void) | undefined;

onMounted(() => {
    removeFlashListener = router.on('flash', (event) => {
        const flash = (event as CustomEvent).detail?.flash;
        const data = flash?.toast as
            | { type: string; message: string }
            | undefined;

        staleMoveMessage.value = data?.type === 'error' ? data.message : null;
    });
});

onUnmounted(() => {
    removeFlashListener?.();
});
</script>

<template>
    <Head title="Production Board" />

    <div
        class="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4"
    >
        <h1 class="text-[28px] leading-[1.2] font-semibold">
            Production Board
        </h1>

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
            v-if="staleMoveMessage"
            title="This move didn't go through"
            :errors="[staleMoveMessage]"
        />

        <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
            <Card v-for="stage in stages" :key="stage.value">
                <CardContent class="flex flex-col gap-1">
                    <span class="text-[28px] leading-[1.2] font-semibold">
                        {{ stageCounts[stage.value] }}
                    </span>
                    <span class="text-sm font-semibold">{{ stage.label }}</span>
                </CardContent>
            </Card>
        </div>

        <Tabs :model-value="activeFilter" @update:model-value="onTabChange">
            <TabsList>
                <TabsTrigger value="all">All</TabsTrigger>
                <TabsTrigger value="rush">Rush</TabsTrigger>
                <TabsTrigger
                    v-for="stage in stages"
                    :key="stage.value"
                    :value="stage.value"
                >
                    {{ stage.label }}
                </TabsTrigger>
            </TabsList>
        </Tabs>

        <div
            class="border-sidebar-border/70 dark:border-sidebar-border overflow-hidden rounded-xl border"
        >
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Urgency</TableHead>
                        <TableHead>Job Order</TableHead>
                        <TableHead>Customer</TableHead>
                        <TableHead>Description</TableHead>
                        <TableHead>Stage</TableHead>
                        <TableHead>Due</TableHead>
                        <TableHead class="text-right">Actions</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableEmpty
                        v-if="filteredJobOrders.length === 0"
                        :colspan="7"
                    >
                        <div
                            v-if="jobOrders.length === 0"
                            class="flex flex-col items-center gap-1 text-center"
                        >
                            <p class="font-semibold">Nothing in production</p>
                            <p class="text-muted-foreground">
                                Job orders appear here automatically once a file
                                is validated or a design is approved.
                            </p>
                        </div>
                        <p v-else-if="activeFilter === 'rush'">
                            No rush orders right now.
                        </p>
                        <p v-else>No job orders in {{ activeFilterLabel }}.</p>
                    </TableEmpty>
                    <TableRow
                        v-for="jobOrder in filteredJobOrders"
                        v-else
                        :key="jobOrder.id"
                        :class="{
                            'bg-amber-50 dark:bg-amber-950/20':
                                jobOrder.is_rush,
                        }"
                    >
                        <TableCell>
                            <Badge
                                v-if="jobOrder.is_rush"
                                variant="outline"
                                class="border-amber-600/40 text-amber-600 dark:text-amber-400"
                            >
                                <Zap class="size-3" />
                                Rush
                            </Badge>
                            <Badge
                                v-else
                                variant="outline"
                                class="border-green-600/40 text-green-600 dark:text-green-400"
                            >
                                Normal
                            </Badge>
                        </TableCell>
                        <TableCell>
                            <span class="tabular-nums">
                                {{ jobOrder.number ?? '—' }}
                            </span>
                        </TableCell>
                        <TableCell>
                            {{ jobOrder.queue_entry?.customer?.name }}
                        </TableCell>
                        <TableCell>{{ jobOrder.description }}</TableCell>
                        <TableCell>
                            <Badge
                                v-if="jobOrder.status === 'for_production'"
                                variant="outline"
                            >
                                For Production
                            </Badge>
                            <Badge
                                v-else-if="jobOrder.status === 'printing'"
                                variant="secondary"
                            >
                                Printing
                            </Badge>
                            <Badge
                                v-else-if="jobOrder.status === 'quality_check'"
                                variant="secondary"
                            >
                                Quality Check
                            </Badge>
                            <Badge
                                v-else-if="
                                    jobOrder.status === 'ready_for_pickup'
                                "
                                class="text-green-600 dark:text-green-400"
                            >
                                Ready for Pickup
                            </Badge>
                        </TableCell>
                        <TableCell>
                            <div class="flex flex-col">
                                <span>{{ dueLabel(jobOrder.due_at) }}</span>
                                <span
                                    v-if="isOverdue(jobOrder.due_at)"
                                    class="text-sm text-amber-600 dark:text-amber-400"
                                >
                                    Overdue
                                </span>
                            </div>
                        </TableCell>
                        <TableCell class="text-right">
                            <div class="flex items-center justify-end gap-2">
                                <Form
                                    v-if="
                                        jobOrder.status !== 'ready_for_pickup'
                                    "
                                    v-bind="
                                        ProductionStageController.advance.form(
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
                                        :data-test="`advance-job-order-${jobOrder.id}-button`"
                                    >
                                        Advance to
                                        {{ nextStageLabel(jobOrder.status) }}
                                    </Button>
                                </Form>
                                <p v-else class="text-muted-foreground text-sm">
                                    Awaiting release
                                </p>

                                <Dialog
                                    v-if="jobOrder.status !== 'for_production'"
                                >
                                    <DialogTrigger as-child>
                                        <Button
                                            type="button"
                                            size="sm"
                                            variant="outline"
                                            :data-test="`send-back-job-order-${jobOrder.id}-button`"
                                        >
                                            Send Back to
                                            {{
                                                previousStageLabel(
                                                    jobOrder.status,
                                                )
                                            }}
                                        </Button>
                                    </DialogTrigger>
                                    <DialogContent>
                                        <Form
                                            v-bind="
                                                ProductionStageController.sendBack.form(
                                                    jobOrder.id,
                                                )
                                            "
                                            :options="{ preserveScroll: true }"
                                            class="space-y-4"
                                            v-slot="{ errors, processing }"
                                        >
                                            <DialogHeader>
                                                <DialogTitle>
                                                    Send back to
                                                    {{
                                                        previousStageLabel(
                                                            jobOrder.status,
                                                        )
                                                    }}?
                                                </DialogTitle>
                                            </DialogHeader>

                                            <p
                                                class="text-muted-foreground text-sm"
                                            >
                                                This is recorded on the
                                                production log with your name
                                                and the reason below.
                                            </p>

                                            <div class="grid gap-2">
                                                <Label
                                                    :for="`send-back-reason-${jobOrder.id}`"
                                                >
                                                    Reason
                                                </Label>
                                                <Textarea
                                                    :id="`send-back-reason-${jobOrder.id}`"
                                                    name="reason"
                                                    placeholder="e.g. Colour banding on the second pass — needs a reprint"
                                                />
                                                <InputError
                                                    :message="errors.reason"
                                                />
                                            </div>

                                            <DialogFooter class="gap-2">
                                                <DialogClose as-child>
                                                    <Button
                                                        type="button"
                                                        variant="secondary"
                                                    >
                                                        Cancel
                                                    </Button>
                                                </DialogClose>
                                                <Button
                                                    type="submit"
                                                    :disabled="processing"
                                                    :data-test="`confirm-send-back-${jobOrder.id}-button`"
                                                >
                                                    <Spinner
                                                        v-if="processing"
                                                    />
                                                    Send Back
                                                </Button>
                                            </DialogFooter>
                                        </Form>
                                    </DialogContent>
                                </Dialog>
                            </div>
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>
    </div>
</template>

<script setup lang="ts">
import { Form, Head, Link, usePoll } from '@inertiajs/vue3';
import {
    PackageCheck,
    Plus,
    RefreshCw,
    Ticket,
    UserRound,
    Zap,
} from '@lucide/vue';
import { reactive, ref } from 'vue';
import JobOrderReleaseController from '@/actions/App/Http/Controllers/FrontlineStaff/JobOrderReleaseController';
import QueueEntryController from '@/actions/App/Http/Controllers/FrontlineStaff/QueueEntryController';
import DataTableCard from '@/components/DataTableCard.vue';
import EmptyState from '@/components/EmptyState.vue';
import InputError from '@/components/InputError.vue';
import PageContainer from '@/components/PageContainer.vue';
import PageHeader from '@/components/PageHeader.vue';
import ReplaceJobOrderFileDialog from '@/components/ReplaceJobOrderFileDialog.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button, buttonVariants } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { Switch } from '@/components/ui/switch';
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
import { dashboard, newVisit } from '@/routes/frontline-staff';
import { index as queueEntriesIndex } from '@/routes/frontline-staff/queue-entries';
import { queueNumberLabel } from '@/lib/utils';

interface QueueEntryCustomer {
    id: number;
    name: string;
}

interface JobOrderRecord {
    id: number;
    number: string | null;
    description: string;
    type: string;
    status: string;
    validation_failure_reason: string | null;
    assigned_artist: {
        id: number;
        name: string;
        artist_label: string | null;
    } | null;
    payment_status: string;
    released_at: string | null;
}

interface QueueEntryRecord {
    id: number;
    customer_id: number;
    queue_number: number;
    status: 'waiting' | 'serving' | 'done';
    customer: QueueEntryCustomer;
    job_orders: JobOrderRecord[];
}

interface ReadyForPickupSummary {
    count: number;
    items: Array<{ number: string | null }>;
}

const props = defineProps<{
    queueEntries: QueueEntryRecord[];
    readyForPickup: ReadyForPickupSummary;
}>();

defineOptions({
    layout: {
        navItems: frontlineStaffNavItems,
        breadcrumbs: [
            {
                title: 'Queue',
                href: queueEntriesIndex(),
            },
        ],
    },
});

// D-13/D-14: the same derived, self-correcting ready-for-pickup alert as the
// Frontline Dashboard, surfaced here so staff already on this page see it
// without navigating away.
usePoll(5000, { only: ['readyForPickup'] });

/**
 * Where to send the customer once an artist has taken the job.
 *
 * The label ("Artist 3") is what the shop floor is signposted with, so it
 * leads; the artist's own name follows for staff. An artist created before
 * labels existed falls back to their name alone rather than showing nothing.
 */
function artistDestination(jobOrder: JobOrderRecord): string {
    const artist = jobOrder.assigned_artist;

    if (artist === null) {
        return 'Assigned';
    }

    return artist.artist_label
        ? `${artist.artist_label} — ${artist.name}`
        : artist.name;
}

function readyForPickupBannerHeading(): string {
    const count = props.readyForPickup.count;

    return `${count} job order${count === 1 ? '' : 's'} ready for pickup`;
}

function readyForPickupBannerBody(): string {
    const { count, items } = props.readyForPickup;
    const names = items.map((item) => item.number ?? '—');
    const extra = count - names.length;

    let subject = names.join(', ');

    if (extra > 0) {
        subject += ` and ${extra} more`;
    }

    return `${subject} ${count === 1 ? 'is' : 'are'} waiting on the shelf.`;
}

// The Add Job Order dialog's Type A/B selection is tracked locally per row
// (keyed by queue entry id) purely to drive the conditional file field —
// the RadioGroup's `name="type"` prop mirrors this value into a hidden
// native input so the surrounding Inertia <Form> still submits it normally.
const jobOrderTypeByEntry = reactive<Record<number, 'type_a' | 'type_b'>>({});

/**
 * Which entry's Add Job Order dialog is open, if any.
 *
 * Controlled rather than left to the Dialog's own state so the dialog can
 * be closed from `onSuccess` -- an uncontrolled dialog stays open behind
 * the toast after the job order is created, and the staff member is left
 * looking at a form they already submitted.
 */
const openJobOrderDialog = ref<number | null>(null);

function setJobOrderDialog(entryId: number, open: boolean): void {
    openJobOrderDialog.value = open ? entryId : null;
}

function jobOrderType(entryId: number): 'type_a' | 'type_b' {
    return jobOrderTypeByEntry[entryId] ?? 'type_a';
}

function setJobOrderType(entryId: number, value: unknown): void {
    jobOrderTypeByEntry[entryId] = value === 'type_b' ? 'type_b' : 'type_a';
}

function jobOrderTypeLabel(type: string): string {
    return type === 'type_a' ? 'Type A' : 'Type B';
}

// The four stages that actually mean "on the press". Enumerated rather
// than left as a catch-all v-else: everything else falling through would
// label in_consultation / in_design / pending_review / design_approved
// job orders "In Production", telling Frontline Staff a design still being
// consulted with the customer is already printing.
const PRODUCTION_STATUSES = [
    'for_production',
    'printing',
    'quality_check',
    'ready_for_pickup',
];

function isInProduction(status: string): boolean {
    return PRODUCTION_STATUSES.includes(status);
}

// Mirrors JobOrderReleaseController::store's own server-side gate (POS-09) —
// this only decides whether to render the button; the controller re-checks
// production stage and payment_status independently and rejects a direct
// request regardless of what this predicate returns. The ready_for_pickup
// check keeps a fully-paid order still on the press from rendering a live
// "Release to Customer" button here.
function isReleaseEligible(jobOrder: JobOrderRecord): boolean {
    return (
        jobOrder.status === 'ready_for_pickup' &&
        (jobOrder.payment_status === 'paid' ||
            jobOrder.payment_status === 'on_credit') &&
        jobOrder.released_at === null
    );
}
</script>

<template>
    <Head title="Queue" />

    <PageContainer>
        <PageHeader
            title="Queue"
            description="Today's visits in the order they arrived. Call the next customer, then mark them done."
        />

        <Alert v-if="readyForPickup.count > 0">
            <PackageCheck class="size-4" />
            <AlertTitle>{{ readyForPickupBannerHeading() }}</AlertTitle>
            <AlertDescription class="flex flex-col gap-2">
                <p>{{ readyForPickupBannerBody() }}</p>
                <Link
                    :href="dashboard()"
                    class="font-medium underline underline-offset-4"
                >
                    View ready orders
                </Link>
            </AlertDescription>
        </Alert>

        <DataTableCard>
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Queue Number</TableHead>
                        <TableHead>Customer</TableHead>
                        <TableHead>Job Orders</TableHead>
                        <TableHead>Status</TableHead>
                        <TableHead class="text-right">Actions</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableEmpty v-if="queueEntries.length === 0" :colspan="5">
                        <EmptyState
                            :icon="Ticket"
                            title="No queue entries yet today"
                            description="Queue numbers reset each business day. Start a visit to create the first one."
                        >
                            <template #actions>
                                <Link
                                    :href="newVisit()"
                                    :class="buttonVariants({ size: 'sm' })"
                                >
                                    New Visit
                                </Link>
                            </template>
                        </EmptyState>
                    </TableEmpty>
                    <TableRow
                        v-for="entry in queueEntries"
                        v-else
                        :key="entry.id"
                    >
                        <TableCell>
                            <span
                                class="bg-secondary text-secondary-foreground inline-flex size-9 items-center justify-center rounded-lg text-base font-bold tabular-nums"
                            >
                                {{ queueNumberLabel(entry.queue_number) }}
                            </span>
                        </TableCell>
                        <TableCell>{{ entry.customer.name }}</TableCell>
                        <TableCell>
                            <div class="flex flex-col gap-1">
                                <div
                                    v-for="jobOrder in entry.job_orders"
                                    :key="jobOrder.id"
                                    class="flex flex-wrap items-center gap-2"
                                >
                                    <span
                                        class="text-muted-foreground tabular-nums"
                                        >{{ jobOrder.number ?? '—' }}</span
                                    >
                                    <span>{{ jobOrder.description }}</span>
                                    <Badge variant="outline">
                                        {{ jobOrderTypeLabel(jobOrder.type) }}
                                    </Badge>
                                    <Badge
                                        v-if="jobOrder.status === 'intake'"
                                        variant="outline"
                                    >
                                        Waiting for an Artist
                                    </Badge>
                                    <Badge
                                        v-else-if="
                                            jobOrder.status ===
                                            'ready_for_production'
                                        "
                                        class="text-green-600 dark:text-green-400"
                                    >
                                        Ready for Production
                                    </Badge>
                                    <Badge
                                        v-else-if="
                                            jobOrder.status === 'assigned' ||
                                            jobOrder.status ===
                                                'in_consultation'
                                        "
                                        variant="default"
                                        :data-test="`job-order-${jobOrder.id}-artist-badge`"
                                    >
                                        <UserRound class="size-3" />
                                        {{ artistDestination(jobOrder) }}
                                    </Badge>
                                    <Badge
                                        v-else-if="
                                            jobOrder.status ===
                                            'validation_failed'
                                        "
                                        variant="destructive"
                                    >
                                        Validation Failed
                                    </Badge>
                                    <Badge
                                        v-else-if="
                                            jobOrder.released_at !== null
                                        "
                                        class="text-green-600 dark:text-green-400"
                                    >
                                        Released
                                    </Badge>
                                    <Badge
                                        v-else-if="
                                            isInProduction(jobOrder.status)
                                        "
                                        variant="secondary"
                                    >
                                        In Production
                                    </Badge>
                                    <Badge v-else variant="secondary">
                                        In Design
                                    </Badge>
                                    <ReplaceJobOrderFileDialog
                                        v-if="
                                            jobOrder.status ===
                                            'validation_failed'
                                        "
                                        :job-order-id="jobOrder.id"
                                    >
                                        <Button
                                            variant="outline"
                                            size="icon"
                                            data-test="replace-file-button"
                                        >
                                            <RefreshCw class="size-4" />
                                            <span class="sr-only">
                                                Replace File
                                            </span>
                                        </Button>
                                    </ReplaceJobOrderFileDialog>
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
                                </div>
                            </div>
                        </TableCell>
                        <TableCell>
                            <Badge
                                v-if="entry.status === 'waiting'"
                                variant="outline"
                            >
                                Waiting
                            </Badge>
                            <Badge
                                v-else-if="entry.status === 'serving'"
                                variant="default"
                            >
                                Serving
                            </Badge>
                            <Badge
                                v-else-if="entry.status === 'done'"
                                class="text-green-600 dark:text-green-400"
                            >
                                Done
                            </Badge>
                        </TableCell>
                        <TableCell>
                            <div class="flex items-center justify-end gap-2">
                                <Dialog
                                    :open="openJobOrderDialog === entry.id"
                                    @update:open="
                                        (open) =>
                                            setJobOrderDialog(entry.id, open)
                                    "
                                >
                                    <DialogTrigger as-child>
                                        <Button
                                            variant="outline"
                                            :data-test="`add-job-order-${entry.id}-button`"
                                        >
                                            <Plus class="size-4" />
                                            Add Job Order
                                        </Button>
                                    </DialogTrigger>
                                    <DialogContent>
                                        <Form
                                            v-bind="
                                                QueueEntryController.addJobOrder.form(
                                                    entry.id,
                                                )
                                            "
                                            :options="{
                                                preserveScroll: true,
                                            }"
                                            @success="
                                                setJobOrderDialog(
                                                    entry.id,
                                                    false,
                                                )
                                            "
                                            class="space-y-6"
                                            v-slot="{ errors, processing }"
                                        >
                                            <DialogHeader>
                                                <DialogTitle>
                                                    Add Job Order
                                                </DialogTitle>
                                            </DialogHeader>

                                            <div class="grid gap-2">
                                                <Label
                                                    :for="`add-job-order-description-${entry.id}`"
                                                >
                                                    Product / Service
                                                </Label>
                                                <Input
                                                    :id="`add-job-order-description-${entry.id}`"
                                                    name="description"
                                                    required
                                                    placeholder="Tarpaulin, 3x5ft"
                                                />
                                                <InputError
                                                    :message="
                                                        errors.description
                                                    "
                                                />
                                            </div>

                                            <div class="grid gap-2">
                                                <Label>Job Order Type</Label>
                                                <RadioGroup
                                                    name="type"
                                                    :model-value="
                                                        jobOrderType(entry.id)
                                                    "
                                                    @update:model-value="
                                                        (value) =>
                                                            setJobOrderType(
                                                                entry.id,
                                                                value,
                                                            )
                                                    "
                                                >
                                                    <div
                                                        class="flex items-center gap-2"
                                                    >
                                                        <RadioGroupItem
                                                            :id="`add-job-order-type-a-${entry.id}`"
                                                            value="type_a"
                                                        />
                                                        <Label
                                                            :for="`add-job-order-type-a-${entry.id}`"
                                                        >
                                                            Type A — Print-ready
                                                            file
                                                        </Label>
                                                    </div>
                                                    <div
                                                        class="flex items-center gap-2"
                                                    >
                                                        <RadioGroupItem
                                                            :id="`add-job-order-type-b-${entry.id}`"
                                                            value="type_b"
                                                        />
                                                        <Label
                                                            :for="`add-job-order-type-b-${entry.id}`"
                                                        >
                                                            Type B — Needs
                                                            consultation
                                                        </Label>
                                                    </div>
                                                </RadioGroup>
                                                <InputError
                                                    :message="errors.type"
                                                />
                                            </div>

                                            <div
                                                v-if="
                                                    jobOrderType(entry.id) ===
                                                    'type_a'
                                                "
                                                class="grid gap-2"
                                            >
                                                <Label
                                                    :for="`add-job-order-file-${entry.id}`"
                                                >
                                                    Attach File
                                                </Label>
                                                <Input
                                                    :id="`add-job-order-file-${entry.id}`"
                                                    type="file"
                                                    name="file"
                                                />
                                                <InputError
                                                    :message="errors.file"
                                                />
                                            </div>

                                            <div class="grid gap-2">
                                                <div
                                                    class="flex items-center gap-3"
                                                >
                                                    <!--
                                                        This Form is
                                                        uncontrolled, so the
                                                        name/value pair IS the
                                                        wiring. The explicit
                                                        value="1" is mandatory:
                                                        reka-ui's SwitchRoot
                                                        defaults its hidden
                                                        checkbox value to 'on',
                                                        which fails Laravel's
                                                        boolean rule.
                                                    -->
                                                    <Switch
                                                        :id="`add-job-order-rush-${entry.id}`"
                                                        name="is_rush"
                                                        value="1"
                                                        :data-test="`add-job-order-rush-${entry.id}-switch`"
                                                    />
                                                    <Label
                                                        :for="`add-job-order-rush-${entry.id}`"
                                                        class="flex items-center gap-2"
                                                    >
                                                        <Zap class="size-4" />
                                                        Rush Order
                                                    </Label>
                                                </div>
                                                <p
                                                    class="text-muted-foreground text-sm"
                                                >
                                                    Prioritised in production.
                                                    The Cashier decides whether
                                                    the rush fee is charged.
                                                </p>
                                                <InputError
                                                    :message="errors.is_rush"
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
                                                    :data-test="`submit-add-job-order-${entry.id}-button`"
                                                >
                                                    Add Job Order
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
        </DataTableCard>
    </PageContainer>
</template>

<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { Plus, RefreshCw } from '@lucide/vue';
import { reactive } from 'vue';
import QueueEntryController from '@/actions/App/Http/Controllers/FrontlineStaff/QueueEntryController';
import InputError from '@/components/InputError.vue';
import ReplaceJobOrderFileDialog from '@/components/ReplaceJobOrderFileDialog.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
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
import { index as queueEntriesIndex } from '@/routes/frontline-staff/queue-entries';

interface QueueEntryCustomer {
    id: number;
    name: string;
}

interface JobOrderRecord {
    id: number;
    description: string;
    type: string;
    status: string;
    validation_failure_reason: string | null;
    assigned_artist: { id: number; name: string } | null;
}

interface QueueEntryRecord {
    id: number;
    customer_id: number;
    queue_number: number;
    status: 'waiting' | 'serving' | 'done';
    customer: QueueEntryCustomer;
    job_orders: JobOrderRecord[];
}

defineProps<{
    queueEntries: QueueEntryRecord[];
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

// The Add Job Order dialog's Type A/B selection is tracked locally per row
// (keyed by queue entry id) purely to drive the conditional file field —
// the RadioGroup's `name="type"` prop mirrors this value into a hidden
// native input so the surrounding Inertia <Form> still submits it normally.
const jobOrderTypeByEntry = reactive<Record<number, 'type_a' | 'type_b'>>({});

function jobOrderType(entryId: number): 'type_a' | 'type_b' {
    return jobOrderTypeByEntry[entryId] ?? 'type_a';
}

function setJobOrderType(entryId: number, value: unknown): void {
    jobOrderTypeByEntry[entryId] = value === 'type_b' ? 'type_b' : 'type_a';
}

function jobOrderTypeLabel(type: string): string {
    return type === 'type_a' ? 'Type A' : 'Type B';
}
</script>

<template>
    <Head title="Queue" />

    <div
        class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4"
    >
        <h1 class="text-[28px] leading-[1.2] font-semibold">Queue</h1>

        <div
            class="border-sidebar-border/70 dark:border-sidebar-border overflow-hidden rounded-xl border"
        >
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
                        No queue entries yet today.
                    </TableEmpty>
                    <TableRow
                        v-for="entry in queueEntries"
                        v-else
                        :key="entry.id"
                    >
                        <TableCell>{{ entry.queue_number }}</TableCell>
                        <TableCell>{{ entry.customer.name }}</TableCell>
                        <TableCell>
                            <div class="flex flex-col gap-1">
                                <div
                                    v-for="jobOrder in entry.job_orders"
                                    :key="jobOrder.id"
                                    class="flex flex-wrap items-center gap-2"
                                >
                                    <span>{{ jobOrder.description }}</span>
                                    <Badge variant="outline">
                                        {{ jobOrderTypeLabel(jobOrder.type) }}
                                    </Badge>
                                    <Badge
                                        v-if="jobOrder.status === 'intake'"
                                        variant="outline"
                                    >
                                        Awaiting Assignment
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
                                            jobOrder.status === 'assigned'
                                        "
                                        variant="default"
                                    >
                                        Assigned
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
                                <Form
                                    v-if="entry.status === 'waiting'"
                                    v-bind="
                                        QueueEntryController.callNext.form(
                                            entry.id,
                                        )
                                    "
                                    :options="{ preserveScroll: true }"
                                    v-slot="{ processing }"
                                >
                                    <Button
                                        type="submit"
                                        :disabled="processing"
                                        :data-test="`call-next-${entry.id}-button`"
                                    >
                                        Call Next
                                    </Button>
                                </Form>
                                <Form
                                    v-else-if="entry.status === 'serving'"
                                    v-bind="
                                        QueueEntryController.markDone.form(
                                            entry.id,
                                        )
                                    "
                                    :options="{ preserveScroll: true }"
                                    v-slot="{ processing }"
                                >
                                    <Button
                                        type="submit"
                                        :disabled="processing"
                                        :data-test="`mark-done-${entry.id}-button`"
                                    >
                                        Mark Done
                                    </Button>
                                </Form>

                                <Dialog>
                                    <DialogTrigger as-child>
                                        <Button
                                            variant="outline"
                                            size="icon"
                                            :data-test="`add-job-order-${entry.id}-button`"
                                        >
                                            <Plus class="size-4" />
                                            <span class="sr-only">
                                                Add Job Order
                                            </span>
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
        </div>
    </div>
</template>

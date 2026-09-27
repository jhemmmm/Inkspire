<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import WriteOffApprovalController from '@/actions/App/Http/Controllers/Owner/WriteOffApprovalController';
import {
    AlertDialog,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
    AlertDialogTrigger,
} from '@/components/ui/alert-dialog';
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
import { ownerNavItems } from '@/config/nav/owner';
import { index as writeOffRequestsIndex } from '@/routes/owner/write-off-requests';

interface WriteOffRequest {
    id: number;
    balance: number;
    days_past_due: number | null;
    write_off_reason: string | null;
    write_off_requested_by: { name: string | null };
    write_off_requested_at: string;
    job_order: {
        id: number;
        number: string | null;
        description: string;
        queue_entry: { customer: { name: string | null } | null };
    };
}

defineProps<{
    writeOffRequests: WriteOffRequest[];
}>();

defineOptions({
    layout: {
        navItems: ownerNavItems,
        breadcrumbs: [
            {
                title: 'Write-Off Requests',
                href: writeOffRequestsIndex(),
            },
        ],
    },
});

function money(value: number): string {
    return `₱${Number(value).toLocaleString('en-PH', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })}`;
}
</script>

<template>
    <Head title="Write-Off Requests" />

    <div
        class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4"
    >
        <h1 class="text-[28px] leading-[1.2] font-semibold">Write-Off Requests</h1>

        <div
            class="border-sidebar-border/70 dark:border-sidebar-border overflow-hidden rounded-xl border"
        >
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Job Order</TableHead>
                        <TableHead>Customer</TableHead>
                        <TableHead class="text-right">Outstanding</TableHead>
                        <TableHead class="text-right">Days Past Due</TableHead>
                        <TableHead>Reason</TableHead>
                        <TableHead>Requested By</TableHead>
                        <TableHead>Requested At</TableHead>
                        <TableHead class="text-right">Actions</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableEmpty v-if="writeOffRequests.length === 0" :colspan="8">
                        <div
                            class="flex flex-col items-center gap-1 text-center"
                        >
                            <p class="font-semibold">No write-off requests</p>
                            <p class="text-muted-foreground">
                                Requests appear here when Accounting Staff asks
                                to write off a balance they can't collect.
                            </p>
                        </div>
                    </TableEmpty>
                    <TableRow
                        v-for="writeOffRequest in writeOffRequests"
                        v-else
                        :key="writeOffRequest.id"
                    >
                        <TableCell>
                            <div class="flex flex-col">
                                <span
                                    class="text-muted-foreground text-xs tabular-nums"
                                    >{{
                                        writeOffRequest.job_order.number ?? '—'
                                    }}</span
                                >
                                <span>{{
                                    writeOffRequest.job_order.description
                                }}</span>
                            </div>
                        </TableCell>
                        <TableCell>
                            {{
                                writeOffRequest.job_order.queue_entry.customer
                                    ?.name ?? '—'
                            }}
                        </TableCell>
                        <TableCell class="text-right tabular-nums">
                            {{ money(writeOffRequest.balance) }}
                        </TableCell>
                        <TableCell class="text-right tabular-nums">
                            {{ writeOffRequest.days_past_due ?? '—' }}
                        </TableCell>
                        <TableCell class="max-w-xs">
                            <span class="line-clamp-2">{{
                                writeOffRequest.write_off_reason
                            }}</span>
                        </TableCell>
                        <TableCell>
                            {{ writeOffRequest.write_off_requested_by.name ?? '—' }}
                        </TableCell>
                        <TableCell>
                            {{
                                new Date(
                                    writeOffRequest.write_off_requested_at,
                                ).toLocaleString()
                            }}
                        </TableCell>
                        <TableCell class="text-right">
                            <div class="flex justify-end gap-2">
                                <AlertDialog>
                                    <AlertDialogTrigger as-child>
                                        <Button
                                            variant="destructive"
                                            :data-test="`approve-write-off-${writeOffRequest.id}-button`"
                                        >
                                            Approve Write-Off
                                        </Button>
                                    </AlertDialogTrigger>
                                    <AlertDialogContent>
                                        <AlertDialogHeader>
                                            <AlertDialogTitle>
                                                Write off {{
                                                    money(writeOffRequest.balance)
                                                }} for {{
                                                    writeOffRequest.job_order.number ?? '—'
                                                }}?
                                            </AlertDialogTitle>
                                            <AlertDialogDescription>
                                                This closes the receivable as a loss and marks the job order Written Off. Reminder emails stop. The job order's original total stays on the books for reporting. This can't be undone.
                                                <br />
                                                <br />
                                                Reason: "{{ writeOffRequest.write_off_reason }}"
                                            </AlertDialogDescription>
                                        </AlertDialogHeader>
                                        <AlertDialogFooter>
                                            <AlertDialogCancel>
                                                Cancel
                                            </AlertDialogCancel>
                                            <Form
                                                v-bind="
                                                    WriteOffApprovalController.approve.form(
                                                        writeOffRequest.id,
                                                    )
                                                "
                                                :options="{
                                                    preserveScroll: true,
                                                }"
                                                v-slot="{ processing }"
                                            >
                                                <Button
                                                    type="submit"
                                                    variant="destructive"
                                                    :disabled="processing"
                                                    :data-test="`confirm-approve-write-off-${writeOffRequest.id}-button`"
                                                >
                                                    Approve Write-Off
                                                </Button>
                                            </Form>
                                        </AlertDialogFooter>
                                    </AlertDialogContent>
                                </AlertDialog>

                                <AlertDialog>
                                    <AlertDialogTrigger as-child>
                                        <Button
                                            variant="outline"
                                            :data-test="`reject-write-off-${writeOffRequest.id}-button`"
                                        >
                                            Reject Request
                                        </Button>
                                    </AlertDialogTrigger>
                                    <AlertDialogContent>
                                        <AlertDialogHeader>
                                            <AlertDialogTitle>
                                                Reject this write-off request?
                                            </AlertDialogTitle>
                                            <AlertDialogDescription>
                                                The balance stays active, keeps aging, and reminder emails continue. Accounting can request a write-off again later.
                                            </AlertDialogDescription>
                                        </AlertDialogHeader>
                                        <AlertDialogFooter>
                                            <AlertDialogCancel>
                                                Cancel
                                            </AlertDialogCancel>
                                            <Form
                                                v-bind="
                                                    WriteOffApprovalController.reject.form(
                                                        writeOffRequest.id,
                                                    )
                                                "
                                                :options="{
                                                    preserveScroll: true,
                                                }"
                                                v-slot="{ processing }"
                                            >
                                                <Button
                                                    type="submit"
                                                    :disabled="processing"
                                                    :data-test="`confirm-reject-write-off-${writeOffRequest.id}-button`"
                                                >
                                                    Reject Request
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
        </div>
    </div>
</template>

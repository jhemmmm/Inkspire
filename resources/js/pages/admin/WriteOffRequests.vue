<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import WriteOffApprovalController from '@/actions/App/Http/Controllers/Admin/WriteOffApprovalController';
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
import DataTableCard from '@/components/DataTableCard.vue';
import EmptyState from '@/components/EmptyState.vue';
import PageContainer from '@/components/PageContainer.vue';
import PageHeader from '@/components/PageHeader.vue';
import SearchableSelect, {
    type SearchableOption,
} from '@/components/SearchableSelect.vue';
import TableFilterBar from '@/components/TableFilterBar.vue';
import { Button } from '@/components/ui/button';
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
import { useLivePoll } from '@/composables/useLivePoll';
import { useTableFilter } from '@/composables/useTableFilter';
import { adminNavItems } from '@/config/nav/admin';
import { index as writeOffRequestsIndex } from '@/routes/admin/write-off-requests';

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

const props = defineProps<{
    writeOffRequests: WriteOffRequest[];
}>();

// A request Accounting just raised shows up without a reload.
useLivePoll(['writeOffRequests']);

defineOptions({
    layout: {
        navItems: adminNavItems,
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

const ALL = 'all';

const requesterOptions = computed<SearchableOption[]>(() => {
    const names = Array.from(
        new Set(
            props.writeOffRequests
                .map((request) => request.write_off_requested_by.name)
                .filter((name): name is string => name !== null),
        ),
    ).sort((a, b) => a.localeCompare(b));

    return [
        { value: ALL, label: 'All requesters' },
        ...names.map((name) => ({ value: name, label: name })),
    ];
});

const requesterFilter = ref(ALL);

const { searchTerm, filtered: filteredWriteOffRequests } = useTableFilter(
    () => props.writeOffRequests,
    (request) => [
        request.job_order.number,
        request.job_order.queue_entry.customer?.name,
        request.job_order.description,
        request.write_off_reason,
        request.write_off_requested_by.name,
    ],
    {
        filters: [
            (request) =>
                requesterFilter.value === ALL ||
                request.write_off_requested_by.name === requesterFilter.value,
        ],
    },
);

const filtersActive = computed(
    () => searchTerm.value.trim() !== '' || requesterFilter.value !== ALL,
);

function clearFilters(): void {
    searchTerm.value = '';
    requesterFilter.value = ALL;
}
</script>

<template>
    <Head title="Write-Off Requests" />

    <PageContainer>
        <PageHeader
            title="Write-Off Requests"
            description="Receivables staff consider uncollectable. Approving one writes off the balance for good."
        />

        <TableFilterBar
            v-if="writeOffRequests.length > 0"
            v-model:search="searchTerm"
            search-label="Search write-off requests"
            search-placeholder="Job order, customer, reason or requester"
            :shown="filteredWriteOffRequests.length"
            :total="writeOffRequests.length"
            :active="filtersActive"
            @clear="clearFilters"
        >
            <div class="flex min-w-0 flex-col gap-2 @lg:w-56">
                <Label for="write-off-requester-filter">Requested by</Label>
                <SearchableSelect
                    id="write-off-requester-filter"
                    v-model="requesterFilter"
                    :options="requesterOptions"
                    placeholder="All requesters"
                />
            </div>
        </TableFilterBar>

        <DataTableCard>
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
                    <TableEmpty
                        v-if="writeOffRequests.length === 0"
                        :colspan="8"
                    >
                        <EmptyState
                            title="No write-off requests"
                            description="Requests appear here when Accounting Staff asks to write off a balance they can't collect."
                        />
                    </TableEmpty>
                    <TableEmpty
                        v-else-if="filteredWriteOffRequests.length === 0"
                        :colspan="8"
                    >
                        <EmptyState
                            title="No matches"
                            description="No write-off requests match that search or the Requested By filter."
                        >
                            <template #actions>
                                <Button
                                    type="button"
                                    variant="secondary"
                                    data-test="clear-write-off-filters-button"
                                    @click="clearFilters"
                                >
                                    Clear filters
                                </Button>
                            </template>
                        </EmptyState>
                    </TableEmpty>
                    <TableRow
                        v-for="writeOffRequest in filteredWriteOffRequests"
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
                            {{
                                writeOffRequest.write_off_requested_by.name ??
                                '—'
                            }}
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
                                                Write off
                                                {{
                                                    money(
                                                        writeOffRequest.balance,
                                                    )
                                                }}
                                                for
                                                {{
                                                    writeOffRequest.job_order
                                                        .number ?? '—'
                                                }}?
                                            </AlertDialogTitle>
                                            <AlertDialogDescription>
                                                This closes the receivable as a
                                                loss and marks the job order
                                                Written Off. Reminder emails
                                                stop. The job order's original
                                                total stays on the books for
                                                reporting. This can't be undone.
                                                <br />
                                                <br />
                                                Reason: "{{
                                                    writeOffRequest.write_off_reason
                                                }}"
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
                                                The balance stays active, keeps
                                                aging, and reminder emails
                                                continue. Accounting can request
                                                a write-off again later.
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
        </DataTableCard>
    </PageContainer>
</template>

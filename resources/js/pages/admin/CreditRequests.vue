<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import CreditApprovalController from '@/actions/App/Http/Controllers/Admin/CreditApprovalController';
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
import { useTableFilter } from '@/composables/useTableFilter';
import { adminNavItems } from '@/config/nav/admin';
import { index as creditRequestsIndex } from '@/routes/admin/credit-requests';

interface CreditRequest {
    id: number;
    balance: number;
    requested_by: { name: string };
    created_at: string;
    job_order: {
        id: number;
        number: string | null;
        description: string;
        queue_entry: { customer: { name: string } };
    };
}

const props = defineProps<{
    creditRequests: CreditRequest[];
}>();

defineOptions({
    layout: {
        navItems: adminNavItems,
        breadcrumbs: [
            {
                title: 'Credit Requests',
                href: creditRequestsIndex(),
            },
        ],
    },
});

const ALL = 'all';

const requesterOptions = computed<SearchableOption[]>(() => {
    const names = Array.from(
        new Set(
            props.creditRequests.map((request) => request.requested_by.name),
        ),
    ).sort((a, b) => a.localeCompare(b));

    return [
        { value: ALL, label: 'All requesters' },
        ...names.map((name) => ({ value: name, label: name })),
    ];
});

const requesterFilter = ref(ALL);

const { searchTerm, filtered: filteredCreditRequests } = useTableFilter(
    () => props.creditRequests,
    (request) => [
        request.job_order.number,
        request.job_order.queue_entry.customer.name,
        request.job_order.description,
        request.requested_by.name,
    ],
    {
        filters: [
            (request) =>
                requesterFilter.value === ALL ||
                request.requested_by.name === requesterFilter.value,
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
    <Head title="Credit Requests" />

    <PageContainer>
        <PageHeader
            title="Credit Requests"
            description="Staff have asked to release these job orders on credit. Approving one opens a receivable."
        />

        <TableFilterBar
            v-if="creditRequests.length > 0"
            v-model:search="searchTerm"
            search-label="Search credit requests"
            search-placeholder="Job order, customer or requester"
            :shown="filteredCreditRequests.length"
            :total="creditRequests.length"
            :active="filtersActive"
            @clear="clearFilters"
        >
            <div class="flex min-w-0 flex-col gap-2 @lg:w-56">
                <Label for="credit-request-requester-filter">
                    Requested by
                </Label>
                <SearchableSelect
                    id="credit-request-requester-filter"
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
                        <TableHead>Amount</TableHead>
                        <TableHead>Requested By</TableHead>
                        <TableHead>Requested At</TableHead>
                        <TableHead class="text-right">Actions</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableEmpty v-if="creditRequests.length === 0" :colspan="6">
                        <EmptyState
                            title="No credit requests"
                            description="Requests will appear here when a Cashier places a job order On Credit."
                        />
                    </TableEmpty>
                    <TableEmpty
                        v-else-if="filteredCreditRequests.length === 0"
                        :colspan="6"
                    >
                        <EmptyState
                            title="No matches"
                            description="No credit requests match that search or the Requested By filter."
                        >
                            <template #actions>
                                <Button
                                    type="button"
                                    variant="secondary"
                                    data-test="clear-credit-request-filters-button"
                                    @click="clearFilters"
                                >
                                    Clear filters
                                </Button>
                            </template>
                        </EmptyState>
                    </TableEmpty>
                    <TableRow
                        v-for="creditRequest in filteredCreditRequests"
                        v-else
                        :key="creditRequest.id"
                    >
                        <TableCell>
                            <div class="flex flex-col">
                                <span
                                    class="text-muted-foreground text-xs tabular-nums"
                                    >{{
                                        creditRequest.job_order.number ?? '—'
                                    }}</span
                                >
                                <span>{{
                                    creditRequest.job_order.description
                                }}</span>
                            </div>
                        </TableCell>
                        <TableCell>
                            {{
                                creditRequest.job_order.queue_entry.customer
                                    .name
                            }}
                        </TableCell>
                        <TableCell>
                            ₱{{ Number(creditRequest.balance).toFixed(2) }}
                        </TableCell>
                        <TableCell>
                            {{ creditRequest.requested_by.name }}
                        </TableCell>
                        <TableCell>
                            {{
                                new Date(
                                    creditRequest.created_at,
                                ).toLocaleString()
                            }}
                        </TableCell>
                        <TableCell class="text-right">
                            <div class="flex justify-end gap-2">
                                <AlertDialog>
                                    <AlertDialogTrigger as-child>
                                        <Button
                                            :data-test="`approve-credit-${creditRequest.id}-button`"
                                        >
                                            Approve Credit
                                        </Button>
                                    </AlertDialogTrigger>
                                    <AlertDialogContent>
                                        <AlertDialogHeader>
                                            <AlertDialogTitle>
                                                Approve On-Credit for ₱{{
                                                    Number(
                                                        creditRequest.balance,
                                                    ).toFixed(2)
                                                }}?
                                            </AlertDialogTitle>
                                            <AlertDialogDescription>
                                                This posts a ₱{{
                                                    Number(
                                                        creditRequest.balance,
                                                    ).toFixed(2)
                                                }}
                                                balance to Accounts Receivable
                                                and marks the job order eligible
                                                for release.
                                            </AlertDialogDescription>
                                        </AlertDialogHeader>
                                        <AlertDialogFooter>
                                            <AlertDialogCancel>
                                                Cancel
                                            </AlertDialogCancel>
                                            <Form
                                                v-bind="
                                                    CreditApprovalController.approve.form(
                                                        creditRequest.id,
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
                                                    :data-test="`confirm-approve-credit-${creditRequest.id}-button`"
                                                >
                                                    Confirm Approval
                                                </Button>
                                            </Form>
                                        </AlertDialogFooter>
                                    </AlertDialogContent>
                                </AlertDialog>

                                <AlertDialog>
                                    <AlertDialogTrigger as-child>
                                        <Button
                                            variant="destructive"
                                            :data-test="`reject-credit-${creditRequest.id}-button`"
                                        >
                                            Reject Credit
                                        </Button>
                                    </AlertDialogTrigger>
                                    <AlertDialogContent>
                                        <AlertDialogHeader>
                                            <AlertDialogTitle>
                                                Reject this credit request?
                                            </AlertDialogTitle>
                                            <AlertDialogDescription>
                                                The job order stays flagged
                                                "Credit Rejected." There's no
                                                automatic fallback — someone
                                                will need to follow up with the
                                                customer on another payment
                                                method.
                                            </AlertDialogDescription>
                                        </AlertDialogHeader>
                                        <AlertDialogFooter>
                                            <AlertDialogCancel>
                                                Cancel
                                            </AlertDialogCancel>
                                            <Form
                                                v-bind="
                                                    CreditApprovalController.reject.form(
                                                        creditRequest.id,
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
                                                    :data-test="`confirm-reject-credit-${creditRequest.id}-button`"
                                                >
                                                    Confirm Rejection
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

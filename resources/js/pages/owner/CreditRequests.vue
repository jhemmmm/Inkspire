<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import CreditApprovalController from '@/actions/App/Http/Controllers/Owner/CreditApprovalController';
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
import { index as creditRequestsIndex } from '@/routes/owner/credit-requests';

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

defineProps<{
    creditRequests: CreditRequest[];
}>();

defineOptions({
    layout: {
        navItems: ownerNavItems,
        breadcrumbs: [
            {
                title: 'Credit Requests',
                href: creditRequestsIndex(),
            },
        ],
    },
});
</script>

<template>
    <Head title="Credit Requests" />

    <div
        class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4"
    >
        <h1 class="text-[28px] leading-[1.2] font-semibold">Credit Requests</h1>

        <div
            class="border-sidebar-border/70 dark:border-sidebar-border overflow-hidden rounded-xl border"
        >
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
                        <div
                            class="flex flex-col items-center gap-1 text-center"
                        >
                            <p class="font-semibold">No credit requests</p>
                            <p class="text-muted-foreground">
                                Requests will appear here when a Cashier places
                                a job order On Credit.
                            </p>
                        </div>
                    </TableEmpty>
                    <TableRow
                        v-for="creditRequest in creditRequests"
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
        </div>
    </div>
</template>

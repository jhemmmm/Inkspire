<script setup lang="ts">
import { Form, Head, Link, setLayoutProps } from '@inertiajs/vue3';
import {
    Banknote,
    Clock,
    FileDown,
    FileMinus,
    FileText,
    ListChecks,
    Printer,
} from '@lucide/vue';
import { computed } from 'vue';
import WriteOffRequestController from '@/actions/App/Http/Controllers/AccountingStaff/WriteOffRequestController';
import InputError from '@/components/InputError.vue';
import PageContainer from '@/components/PageContainer.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import StatusBadge from '@/components/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/dialog';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { accountingStaffNavItems } from '@/config/nav/accounting-staff';
import { useBusinessTime } from '@/composables/useBusinessTime';
import {
    agingBadge,
    BRACKET_LABELS,
    COLLECTION_STATUS_LABELS,
    collectionStatusBadge,
} from '@/lib/accountsReceivable';
import { money } from '@/lib/jobOrders';
import {
    index as accountsReceivableIndex,
    show,
} from '@/routes/accounting-staff/accounts-receivable';
import {
    pdf as collectionLetterPdf,
    show as collectionLetterShow,
} from '@/routes/accounting-staff/accounts-receivable/collection-letter';

interface AccountsReceivableDetail {
    id: number;
    job_order: {
        id: number;
        number: string | null;
        description: string;
        total_amount: number | null;
        queue_entry: { customer: { name: string | null } | null };
    };
    balance: number;
    credit_extended: number;
    aging_bracket: string;
    days_past_due: number | null;
    collection_status: string;
    due_at: string | null;
    write_off_reason: string | null;
    write_off_requested_at: string | null;
    last_reminder_sent_at: string | null;
    approved_at: string | null;
}

const props = defineProps<{
    accountsReceivable: AccountsReceivableDetail;
}>();

defineOptions({
    layout: {
        navItems: accountingStaffNavItems,
    },
});

setLayoutProps({
    breadcrumbs: [
        { title: 'Accounts Receivable', href: accountsReceivableIndex() },
        {
            title: props.accountsReceivable.job_order.number ?? '—',
            href: show.url(props.accountsReceivable.id),
        },
    ],
});

const { formatInstant } = useBusinessTime();

function dateLabel(value: string | null): string {
    if (!value) {
        return '—';
    }

    return formatInstant(value, {
        month: 'long',
        day: 'numeric',
        year: 'numeric',
    });
}

const isTerminal = computed(() =>
    ['paid', 'written_off', 'cancelled'].includes(
        props.accountsReceivable.collection_status,
    ),
);

/**
 * Why this entry reads the status it reads. The server derives collection
 * status from the aging bracket, so the staff member never picks it -- but
 * they do need to know what moves it.
 */
const collectionStatusReason = computed(() => {
    const days = props.accountsReceivable.days_past_due;

    switch (props.accountsReceivable.collection_status) {
        case 'paid':
            return 'This balance has been settled in full, so it is no longer chased.';
        case 'written_off':
            return 'An Admin approved writing this balance off as a loss.';
        case 'cancelled':
            return 'The job order was cancelled, so only its cancellation fee stood.';
        default:
            return days === null
                ? 'This balance is not due yet, so there is nothing to chase.'
                : `This balance is ${days} ${days === 1 ? 'day' : 'days'} past due.`;
    }
});

const hasPendingWriteOff = computed(
    () => props.accountsReceivable.write_off_requested_at !== null,
);
const { formatTimestamp } = useBusinessTime();
</script>

<template>
    <Head
        :title="`${accountsReceivable.job_order.number ?? '—'} — Accounts Receivable`"
    />

    <PageContainer>
        <PageHeader
            :title="accountsReceivable.job_order.number ?? '—'"
            :description="`${accountsReceivable.job_order.queue_entry.customer?.name ?? '—'} · ${accountsReceivable.job_order.description}`"
        >
            <template #actions>
                <StatusBadge
                    :tone="agingBadge(accountsReceivable.aging_bracket)"
                >
                    {{
                        BRACKET_LABELS[accountsReceivable.aging_bracket] ??
                        accountsReceivable.aging_bracket
                    }}
                </StatusBadge>
                <StatusBadge
                    :tone="
                        collectionStatusBadge(
                            accountsReceivable.collection_status,
                        )
                    "
                >
                    {{
                        COLLECTION_STATUS_LABELS[
                            accountsReceivable.collection_status
                        ] ?? accountsReceivable.collection_status
                    }}
                </StatusBadge>
            </template>
        </PageHeader>

        <Alert v-if="hasPendingWriteOff">
            <Clock class="size-4" />
            <AlertTitle>Write-off request submitted</AlertTitle>
            <AlertDescription>
                {{ money(accountsReceivable.balance) }} is awaiting Admin
                approval. Reminder emails continue until it's approved. Reason
                given: "{{ accountsReceivable.write_off_reason }}"
            </AlertDescription>
        </Alert>

        <Card>
            <CardHeader :icon="Banknote">
                <CardTitle>Amounts</CardTitle>
            </CardHeader>
            <CardContent class="grid gap-4">
                <div class="flex items-center justify-between">
                    <span>Credit Extended</span>
                    <span class="tabular-nums">{{
                        money(accountsReceivable.credit_extended)
                    }}</span>
                </div>
                <div class="flex items-center justify-between">
                    <span>Job Order Total</span>
                    <span class="tabular-nums">{{
                        money(accountsReceivable.job_order.total_amount ?? 0)
                    }}</span>
                </div>
                <div class="flex items-center justify-between">
                    <span>Amount Paid</span>
                    <span class="tabular-nums">
                        {{
                            money(
                                (accountsReceivable.job_order.total_amount ??
                                    0) - accountsReceivable.balance,
                            )
                        }}
                    </span>
                </div>
                <div class="flex items-center justify-between border-t pt-4">
                    <span class="font-semibold">Outstanding Balance</span>
                    <span class="text-3xl leading-[1.2] font-bold tabular-nums">
                        {{ money(accountsReceivable.balance) }}
                    </span>
                </div>
                <p class="text-muted-foreground text-sm">
                    Outstanding is the job order total less every completed
                    payment. Payments are recorded at the Cashier counter.
                </p>
            </CardContent>
        </Card>

        <Card>
            <CardHeader :icon="FileText">
                <CardTitle>Account Details</CardTitle>
            </CardHeader>
            <CardContent class="grid grid-cols-2 gap-4">
                <div class="flex flex-col gap-1">
                    <span class="text-sm font-semibold">Approved On</span>
                    <span>{{ dateLabel(accountsReceivable.approved_at) }}</span>
                </div>
                <div class="flex flex-col gap-1">
                    <span class="text-sm font-semibold">Due Date</span>
                    <span>{{ dateLabel(accountsReceivable.due_at) }}</span>
                </div>
                <div class="flex flex-col gap-1">
                    <span class="text-sm font-semibold">Days Past Due</span>
                    <span
                        v-if="accountsReceivable.days_past_due === null"
                        class="text-muted-foreground"
                    >
                        Not yet due
                    </span>
                    <span v-else>{{ accountsReceivable.days_past_due }}</span>
                </div>
                <div class="flex flex-col gap-1">
                    <span class="text-sm font-semibold">Aging Bracket</span>
                    <span>
                        {{
                            BRACKET_LABELS[accountsReceivable.aging_bracket] ??
                            accountsReceivable.aging_bracket
                        }}
                    </span>
                </div>
                <div class="flex flex-col gap-1">
                    <span class="text-sm font-semibold"
                        >Last Reminder Sent</span
                    >
                    <span v-if="accountsReceivable.last_reminder_sent_at">
                        {{
                            formatTimestamp(
                                accountsReceivable.last_reminder_sent_at,
                            )
                        }}
                    </span>
                    <span v-else class="text-muted-foreground"
                        >None sent yet</span
                    >
                </div>
            </CardContent>
        </Card>

        <Card>
            <CardHeader :icon="Clock">
                <CardTitle>Collection Status</CardTitle>
            </CardHeader>
            <CardContent class="flex flex-col gap-4">
                <div class="flex flex-wrap items-center gap-2">
                    <StatusBadge
                        :tone="
                            collectionStatusBadge(
                                accountsReceivable.collection_status,
                            )
                        "
                    >
                        {{
                            COLLECTION_STATUS_LABELS[
                                accountsReceivable.collection_status
                            ] ?? accountsReceivable.collection_status
                        }}
                    </StatusBadge>
                    <span class="text-muted-foreground text-sm">{{
                        collectionStatusReason
                    }}</span>
                </div>

                <p v-if="isTerminal" class="text-muted-foreground text-sm">
                    This entry is closed. It no longer ages and no further
                    reminders are sent for it.
                </p>
                <dl v-else class="flex flex-col gap-1 text-sm">
                    <div class="flex items-baseline justify-between gap-4">
                        <dt class="text-muted-foreground">
                            Not yet due &ndash; 15 days past due
                        </dt>
                        <dd class="font-medium tabular-nums">Pending</dd>
                    </div>
                    <div class="flex items-baseline justify-between gap-4">
                        <dt class="text-muted-foreground">
                            16 &ndash; 30 days
                        </dt>
                        <dd class="font-medium tabular-nums">Follow-up</dd>
                    </div>
                    <div class="flex items-baseline justify-between gap-4">
                        <dt class="text-muted-foreground">
                            31 &ndash; 90 days
                        </dt>
                        <dd class="font-medium tabular-nums">Warning Sent</dd>
                    </div>
                    <div class="flex items-baseline justify-between gap-4">
                        <dt class="text-muted-foreground">Over 90 days</dt>
                        <dd class="font-medium tabular-nums">Collections</dd>
                    </div>
                </dl>
                <p v-if="!isTerminal" class="text-muted-foreground text-sm">
                    Status follows the age of the balance on its own. It closes
                    the moment the balance is paid, or when an Admin approves a
                    write-off.
                </p>
            </CardContent>
        </Card>

        <Card>
            <CardHeader :icon="ListChecks">
                <CardTitle>Actions</CardTitle>
            </CardHeader>
            <CardContent class="flex flex-col gap-2">
                <div class="flex items-center gap-2">
                    <Button
                        v-if="
                            !isTerminal &&
                            accountsReceivable.aging_bracket !== 'current'
                        "
                        as-child
                        variant="outline"
                        class="w-fit"
                    >
                        <Link
                            :href="
                                collectionLetterShow.url(accountsReceivable.id)
                            "
                        >
                            <Printer class="size-4" />
                            Print Collection Letter
                        </Link>
                    </Button>

                    <Button
                        v-if="
                            !isTerminal &&
                            accountsReceivable.aging_bracket !== 'current'
                        "
                        as="a"
                        variant="outline"
                        class="w-fit"
                        :href="collectionLetterPdf.url(accountsReceivable.id)"
                    >
                        <FileDown class="size-4" />
                        Download Letter (PDF)
                    </Button>

                    <Dialog v-if="!isTerminal && !hasPendingWriteOff">
                        <DialogTrigger as-child>
                            <Button variant="outline" class="w-fit">
                                <FileMinus class="size-4" />
                                Request Write-Off
                            </Button>
                        </DialogTrigger>
                        <DialogContent>
                            <Form
                                v-bind="
                                    WriteOffRequestController.store.form(
                                        accountsReceivable.id,
                                    )
                                "
                                :options="{ preserveScroll: true }"
                                class="space-y-4"
                                v-slot="{ errors, processing }"
                            >
                                <DialogHeader>
                                    <DialogTitle>
                                        Request a write-off for
                                        {{ money(accountsReceivable.balance) }}?
                                    </DialogTitle>
                                    <DialogDescription>
                                        An Admin reviews every write-off. Until
                                        they approve it, this balance stays
                                        active and keeps aging.
                                    </DialogDescription>
                                </DialogHeader>

                                <div class="grid gap-2">
                                    <Label for="write-off-reason">Reason</Label>
                                    <Textarea
                                        id="write-off-reason"
                                        name="reason"
                                        rows="4"
                                        placeholder="e.g. Business closed permanently — three collection attempts returned undeliverable"
                                    />
                                    <p class="text-muted-foreground text-sm">
                                        The Admin sees this reason when
                                        deciding. It's recorded in the audit
                                        trail.
                                    </p>
                                    <InputError :message="errors.reason" />
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
                                    >
                                        Submit Request
                                    </Button>
                                </DialogFooter>
                            </Form>
                        </DialogContent>
                    </Dialog>
                </div>
                <p
                    v-if="
                        !isTerminal &&
                        accountsReceivable.aging_bracket === 'current'
                    "
                    class="text-muted-foreground text-sm"
                >
                    A collection letter becomes available once this balance is
                    past due.
                </p>
            </CardContent>
        </Card>
    </PageContainer>
</template>

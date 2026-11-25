<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import CustomerBalanceEmailController from '@/actions/App/Http/Controllers/AccountingStaff/CustomerBalanceEmailController';
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
import { Button } from '@/components/ui/button';
import { useBusinessTime } from '@/composables/useBusinessTime';
import { CUSTOMER_EMAIL_LABELS } from '@/lib/accountsReceivable';
import { money } from '@/lib/jobOrders';

const props = defineProps<{
    receivable: {
        id: number;
        job_order: {
            queue_entry: { customer: { name: string | null } | null };
        };
        balance: number;
        aging_bracket: string;
        customer_email: string;
        customer_emailed_at: string | null;
    };
}>();

const open = ref(false);

const { formatTimestamp } = useBusinessTime();

const customerName = computed(
    () => props.receivable.job_order.queue_entry.customer?.name ?? 'customer',
);
</script>

<template>
    <Dialog v-model:open="open">
        <DialogTrigger as-child>
            <slot />
        </DialogTrigger>
        <DialogContent>
            <Form
                v-bind="
                    CustomerBalanceEmailController.store.form(receivable.id)
                "
                :options="{ preserveScroll: true }"
                class="space-y-4"
                v-slot="{ processing }"
                @success="open = false"
            >
                <DialogHeader>
                    <DialogTitle>
                        Email {{ customerName }} about
                        {{ money(receivable.balance) }}?
                    </DialogTitle>
                    <DialogDescription>
                        This sends
                        {{ CUSTOMER_EMAIL_LABELS[receivable.aging_bracket] }}
                        to
                        <span class="text-foreground font-semibold break-all">{{
                            receivable.customer_email
                        }}</span
                        >. The wording follows how old the balance is.
                    </DialogDescription>
                </DialogHeader>

                <p class="text-muted-foreground text-sm">
                    <template v-if="receivable.customer_emailed_at">
                        Last emailed
                        {{ formatTimestamp(receivable.customer_emailed_at) }}.
                    </template>
                    <template v-else>Not emailed yet.</template>
                </p>

                <DialogFooter class="gap-2">
                    <DialogClose as-child>
                        <Button type="button" variant="secondary">
                            Cancel
                        </Button>
                    </DialogClose>
                    <Button
                        type="submit"
                        :disabled="processing"
                        data-test="send-customer-email-button"
                    >
                        {{ processing ? 'Sending…' : 'Send Email' }}
                    </Button>
                </DialogFooter>
            </Form>
        </DialogContent>
    </Dialog>
</template>

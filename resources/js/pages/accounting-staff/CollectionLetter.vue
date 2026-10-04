<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import PageContainer from '@/components/PageContainer.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { useBusinessTime } from '@/composables/useBusinessTime';

const props = defineProps<{
    jobOrderNumber: string | null;
    customerName: string | null;
    jobOrderDescription: string;
    creditExtended: number;
    amountPaid: number;
    amountDue: number;
    dueDate: string | null;
    daysPastDue: number | null;
    pastDue: boolean;
    letterBody: string | null;
}>();

const appName = usePage().props.name;
const { formatInstant } = useBusinessTime();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Collection Letter', href: '#' }],
    },
});

function money(value: number): string {
    return `₱${Number(value).toLocaleString('en-PH', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })}`;
}

const dueDateLabel = computed(() => {
    if (!props.dueDate) {
        return '—';
    }

    return formatInstant(props.dueDate, {
        month: 'long',
        day: 'numeric',
        year: 'numeric',
    });
});

const today = formatInstant(new Date(), {
    month: 'long',
    day: 'numeric',
    year: 'numeric',
});

// `letterBody` carries literal `{due date}` / `{n}` placeholder tokens from
// AccountsReceivableAgingBracket::letterBody() (D-12) -- interpolated here,
// never persisted anywhere.
const letterBodyText = computed(() => {
    if (!props.letterBody) {
        return '';
    }

    return props.letterBody
        .replaceAll('{due date}', dueDateLabel.value)
        .replaceAll('{n}', String(props.daysPastDue ?? 0));
});

function printLetter(): void {
    window.print();
}
</script>

<template>
    <Head :title="`Collection Letter — ${jobOrderNumber ?? '—'}`" />

    <PageContainer>
        <template v-if="!pastDue">
            <Card class="mx-auto w-full max-w-2xl">
                <CardContent class="flex flex-col gap-2">
                    <h1 class="text-[20px] leading-[1.2] font-semibold">
                        This balance isn't past due yet
                    </h1>
                    <p class="text-muted-foreground text-sm">
                        A collection letter is only printed once the due date
                        has passed. Go back to the entry to check its due date.
                    </p>
                </CardContent>
            </Card>
        </template>

        <template v-else>
            <Button
                variant="outline"
                class="mx-auto w-fit print:hidden"
                @click="printLetter"
            >
                Print Letter
            </Button>

            <Card
                class="mx-auto w-full max-w-2xl print:border-0 print:shadow-none"
            >
                <CardContent class="flex flex-col gap-8">
                    <div class="flex flex-col gap-1">
                        <span class="text-3xl leading-[1.2] font-bold">
                            {{ appName }}
                        </span>
                        <div class="border-t pt-2">
                            <h2 class="text-[20px] leading-[1.2] font-semibold">
                                Statement of Account
                            </h2>
                        </div>
                    </div>

                    <div class="flex flex-col gap-4">
                        <p>{{ today }}</p>
                        <p>To: {{ customerName ?? '—' }}</p>
                        <p>
                            Re: Job Order {{ jobOrderNumber ?? '—' }} —
                            {{ jobOrderDescription }}
                        </p>

                        <p>{{ letterBodyText }}</p>

                        <div class="flex flex-col gap-1 border-t pt-4">
                            <div class="flex items-center justify-between">
                                <span>Credit Extended</span>
                                <span class="tabular-nums">{{
                                    money(creditExtended)
                                }}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span>Amount Paid</span>
                                <span class="tabular-nums">{{
                                    money(amountPaid)
                                }}</span>
                            </div>
                            <div
                                class="flex items-center justify-between pt-2 text-3xl leading-[1.2] font-bold"
                            >
                                <span>Amount Due</span>
                                <span class="tabular-nums">{{
                                    money(amountDue)
                                }}</span>
                            </div>
                        </div>

                        <p class="text-muted-foreground text-sm">
                            Due Date: {{ dueDateLabel }} ·
                            {{ daysPastDue ?? 0 }} days past due
                        </p>

                        <p>
                            Payments are accepted at our counter during business
                            hours. Please bring this notice or quote Job Order
                            {{ jobOrderNumber ?? '—' }}.
                        </p>
                    </div>

                    <div class="flex flex-col gap-4">
                        <p>Sincerely,</p>
                        <div class="flex flex-col gap-1 border-t pt-2">
                            <span>Accounts Receivable</span>
                            <span>{{ appName }}</span>
                        </div>
                    </div>

                    <p class="text-muted-foreground text-sm">
                        Printed {{ today }} · Job Order
                        {{ jobOrderNumber ?? '—' }}
                    </p>
                </CardContent>
            </Card>
        </template>
    </PageContainer>
</template>

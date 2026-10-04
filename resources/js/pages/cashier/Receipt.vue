<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import PageContainer from '@/components/PageContainer.vue';
import TrackingQrCode from '@/components/TrackingQrCode.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { cashierNavItems } from '@/config/nav/cashier';
import { useBusinessTime } from '@/composables/useBusinessTime';
import { dashboard } from '@/routes/cashier';

interface ReceiptPricingEntry {
    id: number;
    name: string;
}

interface ReceiptJobOrder {
    id: number;
    number: string | null;
    description: string;
    is_rush: boolean;
    rush_fee_applied: boolean;
    base_price_snapshot: number | null;
    rush_fee_amount: number | null;
    discount_amount: number | null;
    total_amount: number | null;
    created_at: string;
    pricing_entry: ReceiptPricingEntry | null;
}

interface ReceiptLatestTransaction {
    payment_method: string;
}

/** The VAT already inside the total -- prices are VAT-inclusive. */
interface ReceiptVat {
    rate: number;
    vatable_sales: number;
    amount: number;
}

const props = defineProps<{
    jobOrder: ReceiptJobOrder;
    customerName: string | null;
    latestTransaction: ReceiptLatestTransaction | null;
    amountPaid: number;
    balance: number;
    vat: ReceiptVat;
    cashierName: string | null;
    trackingUrl: string;
}>();

const trackingOrigin = computed(() => new URL(props.trackingUrl).origin);

defineOptions({
    layout: {
        navItems: cashierNavItems,
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: 'Receipt', href: '#' },
        ],
    },
});

function paymentMethodLabel(method: string): string {
    switch (method) {
        case 'cash':
            return 'Cash';
        case 'bank_transfer':
            return 'Bank Transfer';
        case 'gcash':
            return 'GCash';
        case 'maya':
            return 'Maya';
        default:
            return method;
    }
}

/**
 * A rush order that carries no rush fee. Printing a bare "Rush Fee P0.00"
 * on one reads as a mistake, so the receipt says the fee was waived —
 * which is what a zero means once the Cashier has been shown the toggle.
 */
const rushFeeWaived = computed(
    () =>
        props.jobOrder.is_rush &&
        Number(props.jobOrder.rush_fee_amount ?? 0) === 0,
);

function money(value: number | null): string {
    return `₱${Number(value ?? 0).toFixed(2)}`;
}

function printReceipt(): void {
    window.print();
}
const { formatInstant } = useBusinessTime();
</script>

<template>
    <Head :title="`Receipt — ${jobOrder.number ?? jobOrder.id}`" />

    <PageContainer>
        <Button
            variant="outline"
            class="mx-auto w-fit print:hidden"
            @click="printReceipt"
        >
            Print Receipt
        </Button>

        <Card class="mx-auto w-full max-w-md">
            <CardContent class="grid gap-4">
                <div class="flex items-center justify-between">
                    <span class="font-semibold">Job Order No.</span>
                    <span class="tabular-nums">{{
                        jobOrder.number ?? '—'
                    }}</span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="font-semibold">Job Order</span>
                    <span>{{ jobOrder.description }}</span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="font-semibold">Customer</span>
                    <span>{{ customerName ?? '—' }}</span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="font-semibold">Date</span>
                    <span>
                        {{
                            formatInstant(jobOrder.created_at, {
                                dateStyle: 'medium',
                            })
                        }}
                    </span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="font-semibold">Product / Service</span>
                    <span>{{ jobOrder.pricing_entry?.name ?? '—' }}</span>
                </div>
                <div
                    v-if="jobOrder.is_rush"
                    class="flex items-center justify-between"
                >
                    <span class="font-semibold">Priority</span>
                    <span class="font-semibold">Rush Print</span>
                </div>

                <div class="flex flex-col gap-1 border-t pt-4">
                    <div class="flex items-center justify-between">
                        <span>Base Price</span>
                        <span>{{ money(jobOrder.base_price_snapshot) }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span>
                            Rush Fee
                            <span
                                v-if="rushFeeWaived"
                                class="text-muted-foreground"
                            >
                                (waived)
                            </span>
                        </span>
                        <span>{{ money(jobOrder.rush_fee_amount) }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span>Discount</span>
                        <span>{{ money(jobOrder.discount_amount) }}</span>
                    </div>
                    <div
                        class="flex items-center justify-between pt-2 text-3xl leading-[1.2] font-bold"
                    >
                        <span>Total</span>
                        <span>{{ money(jobOrder.total_amount) }}</span>
                    </div>
                    <template v-if="vat.rate > 0">
                        <div
                            class="text-muted-foreground flex items-center justify-between text-sm"
                        >
                            <span>VATable Sales</span>
                            <span class="tabular-nums">{{
                                money(vat.vatable_sales)
                            }}</span>
                        </div>
                        <div
                            class="text-muted-foreground flex items-center justify-between text-sm"
                            data-test="receipt-vat"
                        >
                            <span>VAT ({{ vat.rate }}%)</span>
                            <span class="tabular-nums">{{
                                money(vat.amount)
                            }}</span>
                        </div>
                        <p class="text-muted-foreground text-xs">
                            Prices include VAT.
                        </p>
                    </template>
                </div>

                <div class="flex flex-col gap-1 border-t pt-4">
                    <div class="flex items-center justify-between">
                        <span class="font-semibold">Payment Method</span>
                        <span>
                            {{
                                latestTransaction
                                    ? paymentMethodLabel(
                                          latestTransaction.payment_method,
                                      )
                                    : '—'
                            }}
                        </span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="font-semibold">Amount Paid</span>
                        <span>{{ money(amountPaid) }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="font-semibold">Cashier</span>
                        <span>{{ cashierName ?? '—' }}</span>
                    </div>
                </div>

                <!--
                    The one figure the customer and the counter both need at
                    a glance, so it is boxed and set large. A border, not only
                    a tint, because browsers drop backgrounds when printing.
                -->
                <div
                    class="flex items-center justify-between gap-4 rounded-lg border-2 px-4 py-3"
                    :class="
                        balance > 0
                            ? 'border-destructive/60 bg-destructive/5 text-destructive'
                            : 'border-primary/40 bg-primary/5 text-primary'
                    "
                    data-test="receipt-balance"
                >
                    <span class="flex flex-col">
                        <span class="text-lg font-bold">
                            {{ balance > 0 ? 'Balance Due' : 'Balance' }}
                        </span>
                        <span
                            v-if="balance <= 0"
                            class="text-muted-foreground text-sm"
                        >
                            Paid in full
                        </span>
                    </span>
                    <span class="text-3xl font-extrabold tabular-nums">
                        {{ money(balance) }}
                    </span>
                </div>

                <!--
                    `number` is nullable, and route() drops the query string
                    entirely for a null parameter, so trackingUrl degrades to
                    a bare /track lookup form and the fallback line renders
                    "and enter " with nothing after it. Hide the whole block
                    rather than print a dead QR code.
                -->
                <div
                    v-if="jobOrder.number"
                    class="flex flex-col items-center gap-2 border-t pt-4"
                >
                    <TrackingQrCode :tracking-url="trackingUrl" />
                    <p class="text-muted-foreground text-sm">
                        Scan to track your order
                    </p>
                    <p class="text-muted-foreground text-center text-sm">
                        Or visit {{ trackingOrigin }}/track and enter
                        {{ jobOrder.number }}
                    </p>
                </div>
            </CardContent>
        </Card>
    </PageContainer>
</template>

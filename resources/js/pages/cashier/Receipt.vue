<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import TrackingQrCode from '@/components/TrackingQrCode.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { cashierNavItems } from '@/config/nav/cashier';
import { dashboard } from '@/routes/cashier';

interface ReceiptPricingEntry {
    id: number;
    name: string;
}

interface ReceiptJobOrder {
    id: number;
    number: string | null;
    description: string;
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

const props = defineProps<{
    jobOrder: ReceiptJobOrder;
    customerName: string | null;
    latestTransaction: ReceiptLatestTransaction | null;
    amountPaid: number;
    balance: number;
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

function money(value: number | null): string {
    return `₱${Number(value ?? 0).toFixed(2)}`;
}

function printReceipt(): void {
    window.print();
}
</script>

<template>
    <Head :title="`Receipt — ${jobOrder.number ?? jobOrder.id}`" />

    <div
        class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4"
    >
        <Button
            variant="outline"
            class="mx-auto w-fit print:hidden"
            @click="printReceipt"
        >
            Print Receipt
        </Button>

        <Card class="mx-auto w-full max-w-sm">
            <CardContent class="grid gap-4">
                <div class="flex items-center justify-between">
                    <span class="font-semibold">Job Order No.</span>
                    <span class="tabular-nums">{{ jobOrder.number ?? '—' }}</span>
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
                        {{ new Date(jobOrder.created_at).toLocaleDateString() }}
                    </span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="font-semibold">Product / Service</span>
                    <span>{{ jobOrder.pricing_entry?.name ?? '—' }}</span>
                </div>

                <div class="flex flex-col gap-1 border-t pt-4">
                    <div class="flex items-center justify-between">
                        <span>Base Price</span>
                        <span>{{ money(jobOrder.base_price_snapshot) }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span>Rush Fee</span>
                        <span>{{ money(jobOrder.rush_fee_amount) }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span>Discount</span>
                        <span>{{ money(jobOrder.discount_amount) }}</span>
                    </div>
                    <div
                        class="flex items-center justify-between pt-2 text-[28px] leading-[1.2] font-semibold"
                    >
                        <span>Total</span>
                        <span>{{ money(jobOrder.total_amount) }}</span>
                    </div>
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
                        <span class="font-semibold">Balance</span>
                        <span>{{ money(balance) }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="font-semibold">Cashier</span>
                        <span>{{ cashierName ?? '—' }}</span>
                    </div>
                </div>

                <div class="flex flex-col items-center gap-2 border-t pt-4">
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
    </div>
</template>

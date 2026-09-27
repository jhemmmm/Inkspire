<script setup lang="ts">
/**
 * The Pricing card's figure block on the cashier's Job Order Payment page.
 *
 * Reads top to bottom the way the cashier explains it at the counter: what
 * the job costs, then what has already been handed over, then what is owed
 * today. Remaining Balance is the loudest line because it is the number
 * spoken out loud and the one the customer pays.
 */
defineProps<{
    summary: {
        base: number;
        rushFeeAmount: number;
        discountAmount: number;
        total: number;
        paid: number;
        remaining: number;
    };
}>();

function peso(value: number): string {
    return `₱${Number(value).toLocaleString('en-PH', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })}`;
}
</script>

<template>
    <div
        class="border-border mt-2 flex flex-col gap-3 border-t pt-4 tabular-nums"
    >
        <div class="flex flex-col gap-2">
            <div class="flex items-center justify-between">
                <span class="text-muted-foreground">Base Price</span>
                <span>{{ peso(summary.base) }}</span>
            </div>
            <div class="flex items-center justify-between">
                <span class="text-muted-foreground">Rush Fee</span>
                <span>{{ peso(summary.rushFeeAmount) }}</span>
            </div>
            <div class="flex items-center justify-between">
                <span class="text-muted-foreground">Discount</span>
                <span
                    :class="
                        summary.discountAmount > 0
                            ? 'text-green-600 dark:text-green-400'
                            : ''
                    "
                >
                    {{ summary.discountAmount > 0 ? '−' : ''
                    }}{{ peso(summary.discountAmount) }}
                </span>
            </div>
            <div class="flex items-center justify-between font-medium">
                <span>Total</span>
                <span>{{ peso(summary.total) }}</span>
            </div>
        </div>

        <div class="flex items-center justify-between">
            <span class="text-muted-foreground">Partial Payment</span>
            <span
                :class="
                    summary.paid > 0 ? 'text-green-600 dark:text-green-400' : ''
                "
            >
                {{ summary.paid > 0 ? '−' : '' }}{{ peso(summary.paid) }}
            </span>
        </div>

        <div
            class="border-border flex items-center justify-between border-t pt-3 text-2xl leading-tight font-bold"
        >
            <span>Remaining Balance</span>
            <span>{{ peso(summary.remaining) }}</span>
        </div>

        <p v-if="summary.remaining === 0" class="text-muted-foreground text-sm">
            Nothing left to collect on this job order.
        </p>
    </div>
</template>

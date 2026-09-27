<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { money } from '@/lib/jobOrders';

/**
 * A job order's total for table cells. "Est." marks an intake quote the
 * Cashier has not locked in yet (`total_amount` still null).
 */
defineProps<{
    jobOrder: { total_amount: number | null; display_total: number | null };
}>();
</script>

<template>
    <span class="inline-flex items-center justify-end gap-2 tabular-nums">
        <Badge
            v-if="
                jobOrder.total_amount === null &&
                jobOrder.display_total !== null
            "
            variant="outline"
            class="text-muted-foreground"
        >
            Est.
        </Badge>
        {{
            jobOrder.display_total === null
                ? '—'
                : money(jobOrder.display_total)
        }}
    </span>
</template>

<script lang="ts">
/**
 * One colour per meaning on every portal: a soft tint with coloured text,
 * never coloured text on a solid fill.
 *
 * These live here rather than as extra variants on the generated
 * `ui/badge`, which a regeneration would silently drop.
 */
const TONES = {
    /** Finished or settled: Paid, Done, Released. */
    success: 'bg-success/15 text-success border-transparent',
    /** Waiting on someone: Waiting, Partially Paid, For Production. */
    warning: 'bg-warning/15 text-warning border-transparent',
    /** In progress: Printing, Serving, In Design, On Credit. */
    info: 'bg-primary/10 text-primary border-transparent',
    /** Failed or refused: Validation Failed, Credit Rejected. */
    danger: 'bg-destructive/10 text-destructive border-transparent',
    /** Plain facts and "nothing yet": Unpaid, Written Off. */
    neutral: 'text-muted-foreground',
} as const;

export type StatusTone = keyof typeof TONES;
</script>

<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';

const props = defineProps<{
    tone: StatusTone;
    class?: HTMLAttributes['class'];
}>();
</script>

<template>
    <Badge variant="outline" :class="cn(TONES[tone], props.class)">
        <slot />
    </Badge>
</template>

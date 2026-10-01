<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

const props = defineProps<{
    class?: HTMLAttributes['class'];
}>();
</script>

<template>
    <!--
        Card chrome around a table, with the horizontal scroll *inside* the
        card rather than on the page.

        This is the whole point of the component: a 9-column table used to
        widen the page wrapper, so scrolling right to reach the Actions
        column also dragged the page heading and the filter row off-screen.
        Here the header stays put and only the table moves.

        The column-heading row takes the accent tint so it reads as a
        header band, not as one more data row.

        Also the query container for `EmptyState` inside `TableEmpty` -- see
        `EmptyState.vue` -- so a wide table's empty message sizes to the
        visible card, not the full scroll width.
    -->
    <div
        :class="
            cn(
                'bg-card border-border [&_th]:text-accent-foreground/80 [&_thead]:bg-accent/70 dark:[&_thead]:bg-accent/40 @container overflow-hidden rounded-xl border shadow-sm',
                props.class,
            )
        "
    >
        <div class="w-full overflow-x-auto">
            <slot />
        </div>
    </div>
</template>

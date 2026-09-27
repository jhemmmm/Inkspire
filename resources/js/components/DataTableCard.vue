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
    -->
    <div
        :class="
            cn(
                'bg-card border-border overflow-hidden rounded-xl border shadow-sm',
                props.class,
            )
        "
    >
        <div class="w-full overflow-x-auto">
            <slot />
        </div>
    </div>
</template>

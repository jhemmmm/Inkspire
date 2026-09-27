<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

const props = defineProps<{
    class?: HTMLAttributes['class'];
}>();
</script>

<template>
    <!--
        The single page shell every portal page sits in.

        Two deliberate departures from the per-page wrapper this replaces:
        a capped width, because full-bleed table rows on a wide monitor force
        the eye to track across 2000px to pair a job order with its status;
        and no `overflow-x-auto`, because that made a wide table drag the
        page heading and filters sideways with it. Wide content scrolls
        inside its own box instead -- see DataTableCard.
    -->
    <div
        :class="
            cn(
                'mx-auto flex w-full max-w-[100rem] flex-1 flex-col gap-6 p-4 sm:p-6 lg:p-8',
                props.class,
            )
        "
    >
        <slot />
    </div>
</template>

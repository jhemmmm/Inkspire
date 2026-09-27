<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

const props = defineProps<{
    title: string;
    description?: string;
    class?: HTMLAttributes['class'];
}>();
</script>

<template>
    <!--
        A second-level heading inside a page. Always renders an `h2` under
        PageHeader's `h1`, so the document outline stays sequential for
        screen readers no matter how many sections a page grows.

        The cyan-magenta-yellow bar beside the title echoes the colour bar
        on PageHeader, so a section reads as belonging to the page above it.
    -->
    <div
        :class="
            cn(
                'flex flex-wrap items-end justify-between gap-x-6 gap-y-2',
                props.class,
            )
        "
    >
        <div class="flex min-w-0 items-stretch gap-3">
            <span
                aria-hidden="true"
                class="from-ink-cyan via-ink-magenta to-ink-yellow w-1 shrink-0 rounded-full bg-linear-to-b print:hidden"
            />
            <div class="flex min-w-0 flex-col gap-1">
                <h2 class="text-lg leading-tight font-semibold">
                    {{ title }}
                </h2>
                <p
                    v-if="description"
                    class="text-muted-foreground max-w-prose text-sm"
                >
                    {{ description }}
                </p>
            </div>
        </div>

        <div v-if="$slots.actions" class="flex flex-wrap items-center gap-2">
            <slot name="actions" />
        </div>
    </div>
</template>

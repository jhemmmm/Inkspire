<script setup lang="ts">
import type { Component } from 'vue';

/**
 * A customer page with nothing left to decide on it: one icon, what
 * happened, and (in `#actions`) where to go next, so it is never a dead end.
 */
defineProps<{
    icon: Component;
    /** The icon tile's tint, e.g. `bg-success/15 text-success`. */
    tone: string;
    title: string;
    /** A quiet line above the title, usually the job order number. */
    eyebrow?: string | null;
}>();
</script>

<template>
    <div
        class="flex flex-col items-center gap-3 px-6 py-12 text-center sm:px-10 sm:py-14"
    >
        <span
            :class="[
                tone,
                'flex size-14 items-center justify-center rounded-2xl',
            ]"
        >
            <component :is="icon" class="size-7" />
        </span>
        <p
            v-if="eyebrow"
            class="text-muted-foreground text-xs font-semibold tabular-nums"
        >
            {{ eyebrow }}
        </p>
        <h1
            class="text-2xl leading-tight font-extrabold tracking-tight text-balance"
        >
            {{ title }}
        </h1>
        <p
            class="text-muted-foreground max-w-prose text-sm leading-relaxed sm:text-base"
        >
            <slot />
        </p>
        <div
            v-if="$slots.actions"
            class="mt-3 flex flex-wrap justify-center gap-3"
        >
            <slot name="actions" />
        </div>
    </div>
</template>

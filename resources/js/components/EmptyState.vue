<script setup lang="ts">
import type { Component, HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

const props = defineProps<{
    title: string;
    description?: string;
    icon?: Component;
    class?: HTMLAttributes['class'];
}>();
</script>

<template>
    <!--
        The "nothing here" state, for `TableEmpty` slots and bare panels.
        A named `actions` slot keeps the empty state actionable rather than
        a dead end -- an empty queue should still offer "New Visit".
    -->
    <div
        :class="
            cn('flex flex-col items-center gap-2 py-6 text-center', props.class)
        "
    >
        <span
            v-if="icon"
            class="from-primary/15 via-ink-cyan/10 to-ink-magenta/15 text-primary ring-primary/15 mb-1 flex size-12 items-center justify-center rounded-2xl bg-linear-to-br ring-1 ring-inset"
        >
            <component :is="icon" class="size-6" />
        </span>
        <p class="font-semibold">{{ title }}</p>
        <p v-if="description" class="text-muted-foreground max-w-prose text-sm">
            {{ description }}
        </p>
        <div v-if="$slots.actions" class="mt-1 flex flex-wrap gap-2">
            <slot name="actions" />
        </div>
    </div>
</template>

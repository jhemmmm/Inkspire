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
            class="bg-muted text-muted-foreground flex size-10 items-center justify-center rounded-full"
        >
            <component :is="icon" class="size-5" />
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

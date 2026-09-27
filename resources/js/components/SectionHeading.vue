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
    -->
    <div
        :class="
            cn(
                'flex flex-wrap items-end justify-between gap-x-6 gap-y-2',
                props.class,
            )
        "
    >
        <div class="flex min-w-0 flex-col gap-1">
            <h2 class="text-lg leading-tight font-semibold">{{ title }}</h2>
            <p
                v-if="description"
                class="text-muted-foreground max-w-prose text-sm"
            >
                {{ description }}
            </p>
        </div>

        <div v-if="$slots.actions" class="flex flex-wrap items-center gap-2">
            <slot name="actions" />
        </div>
    </div>
</template>

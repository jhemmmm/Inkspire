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
        The page title, its one-line orientation copy, and a slot for the
        page's primary action.

        Sized `text-2xl sm:text-3xl` rather than the `text-4xl` it replaces:
        at the 18px root that was a 40px heading restating both the sidebar's
        active item and the breadcrumb directly above it, pushing the actual
        work below the fold on a laptop.
    -->
    <div
        :class="
            cn(
                'border-border flex flex-wrap items-end justify-between gap-x-6 gap-y-4 border-b pb-4',
                props.class,
            )
        "
    >
        <div class="flex min-w-0 flex-col gap-1">
            <h1
                class="text-primary text-2xl leading-tight font-extrabold tracking-tight sm:text-3xl"
            >
                {{ title }}
            </h1>
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

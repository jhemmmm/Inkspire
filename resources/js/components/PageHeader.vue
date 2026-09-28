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

        The band is dressed like a press sheet: a CMYK colour bar along the
        top edge and a halftone dot field fading in from the right. Both are
        decoration only -- hidden from screen readers and from print.
    -->
    <div
        :class="
            cn(
                'bg-card border-border relative isolate flex shrink-0 flex-wrap items-end justify-between gap-x-6 gap-y-4 overflow-hidden rounded-2xl border px-5 pt-6 pb-5 shadow-sm sm:px-7 sm:pt-7 sm:pb-6 print:rounded-none print:border-0 print:p-0 print:shadow-none',
                props.class,
            )
        "
    >
        <div
            aria-hidden="true"
            class="absolute inset-x-0 top-0 grid h-1 grid-cols-4 print:hidden"
        >
            <span class="bg-ink-cyan" />
            <span class="bg-ink-magenta" />
            <span class="bg-ink-yellow" />
            <span class="bg-ink-key" />
        </div>
        <div
            aria-hidden="true"
            class="from-primary/8 to-ink-cyan/10 absolute inset-0 -z-10 bg-linear-to-br via-transparent print:hidden"
        />
        <div
            aria-hidden="true"
            class="absolute inset-y-0 right-0 -z-10 w-2/3 bg-[radial-gradient(var(--color-primary)_1.2px,transparent_1.6px)] mask-[linear-gradient(to_left,black,transparent)] bg-size-[14px_14px] opacity-25 dark:opacity-30 print:hidden"
        />

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

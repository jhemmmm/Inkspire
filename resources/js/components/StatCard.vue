<script setup lang="ts">
import { computed } from 'vue';
import type { Component, HTMLAttributes } from 'vue';
import { Card, CardContent } from '@/components/ui/card';
import { cn } from '@/lib/utils';

const props = withDefaults(
    defineProps<{
        label: string;
        value: string | number;
        hint?: string;
        icon?: Component;
        /** `attention` is for figures that are bad news when non-zero. */
        tone?: 'default' | 'attention';
        class?: HTMLAttributes['class'];
    }>(),
    { tone: 'default' },
);

/**
 * A grouped peso figure is three times the width of a count, and these tiles
 * sit four-across on a desktop grid with ~140px for the value. At `text-3xl`
 * a figure like ₱243,000.00 ran off the edge of its card and lost its last
 * digits, so the size steps down with length instead. Truncating is not an
 * option for money, and neither is wrapping — there is nowhere sensible to
 * break a number.
 *
 * Sizes are in this app's 18px root, not the Tailwind default 16px, so
 * `text-base` here is 18px and `text-3xl` is 33.75px.
 *
 * ponytail: character count, not measured fit. It holds to about 14
 * characters (₱1,255,867.89 fits); a wider figure than that would need the
 * value moved to its own full-width row below the icon.
 */
const valueSizeClass = computed((): string => {
    const length = String(props.value).length;

    if (length > 12) {
        return 'text-base';
    }

    if (length > 9) {
        return 'text-xl';
    }

    return length > 7 ? 'text-2xl' : 'text-3xl';
});
</script>

<template>
    <Card :class="cn('h-full', props.class)">
        <CardContent class="flex items-start gap-4">
            <span
                v-if="icon"
                class="bg-accent text-accent-foreground flex size-9 shrink-0 items-center justify-center rounded-lg"
            >
                <component :is="icon" class="size-[18px]" />
            </span>
            <div class="flex min-w-0 flex-col gap-1">
                <!--
                    `tabular-nums` so a column of these tiles keeps its digits
                    on the same vertical rails as the numbers change.
                -->
                <span
                    class="leading-none font-bold tabular-nums"
                    :class="[
                        valueSizeClass,
                        tone === 'attention'
                            ? 'text-destructive'
                            : 'text-foreground',
                    ]"
                >
                    {{ value }}
                </span>
                <span class="text-sm font-semibold">{{ label }}</span>
                <span v-if="hint" class="text-muted-foreground text-xs">
                    {{ hint }}
                </span>
            </div>
        </CardContent>
    </Card>
</template>

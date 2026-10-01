<script setup lang="ts">
import { computed } from 'vue';
import type { Component, HTMLAttributes } from 'vue';
import { Card, CardContent } from '@/components/ui/card';
import { cn } from '@/lib/utils';

type Ink = 'cyan' | 'magenta' | 'yellow' | 'key';

const props = withDefaults(
    defineProps<{
        label: string;
        value: string | number;
        hint?: string;
        icon?: Component;
        /** `attention` is for figures that are bad news when non-zero. */
        tone?: 'default' | 'attention';
        /**
         * The process colour for the icon chip, by what the figure counts —
         * cyan work, magenta people and decisions, yellow money, key
         * records. See the ink note in app.css.
         */
        ink?: Ink;
        class?: HTMLAttributes['class'];
    }>(),
    { tone: 'default', ink: 'cyan' },
);

const INK_CLASSES: Record<Ink | 'attention', { chip: string; glow: string }> = {
    cyan: {
        chip: 'bg-ink-cyan/12 text-ink-cyan ring-ink-cyan/25',
        glow: 'bg-ink-cyan/12',
    },
    magenta: {
        chip: 'bg-ink-magenta/12 text-ink-magenta ring-ink-magenta/25',
        glow: 'bg-ink-magenta/12',
    },
    yellow: {
        chip: 'bg-ink-yellow/12 text-ink-yellow ring-ink-yellow/25',
        glow: 'bg-ink-yellow/12',
    },
    key: {
        chip: 'bg-ink-key/10 text-ink-key ring-ink-key/20',
        glow: 'bg-ink-key/8',
    },
    attention: {
        chip: 'bg-destructive/12 text-destructive ring-destructive/25',
        glow: 'bg-destructive/14',
    },
};

const inkClasses = computed(
    () => INK_CLASSES[props.tone === 'attention' ? 'attention' : props.ink],
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
    <!--
        Only a tile that is itself a link lifts on hover: a bare tile that
        moved under the cursor would promise a click that does nothing.
    -->
    <Card
        :class="
            cn(
                'relative h-full transition duration-200 motion-reduce:transition-none [a:hover>&]:shadow-md motion-safe:[a:hover>&]:-translate-y-0.5',
                tone === 'attention'
                    ? 'border-destructive/40 [a:hover>&]:border-destructive/60'
                    : '[a:hover>&]:border-primary/40',
                props.class,
            )
        "
    >
        <span
            aria-hidden="true"
            class="pointer-events-none absolute -top-12 -right-12 size-28 rounded-full blur-2xl print:hidden"
            :class="inkClasses.glow"
        />
        <CardContent class="relative flex items-start gap-4">
            <span
                v-if="icon"
                class="flex size-10 shrink-0 items-center justify-center rounded-xl ring-1 ring-inset"
                :class="inkClasses.chip"
            >
                <component :is="icon" class="size-5" />
            </span>
            <div class="flex min-w-0 flex-col gap-1">
                <!--
                    `tabular-nums` so a column of these tiles keeps its digits
                    on the same vertical rails as the numbers change.
                -->
                <span
                    class="leading-none font-bold whitespace-nowrap tabular-nums"
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

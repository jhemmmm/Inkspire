<script setup lang="ts">
import { computed } from 'vue';
import { chartValue } from '@/lib/charts';
import type { BreakdownItem, ChartFormat } from '@/lib/charts';

const props = withDefaults(
    defineProps<{
        /** Names the chart for screen readers. */
        label: string;
        items: BreakdownItem[];
        format?: ChartFormat;
        emptyText?: string;
    }>(),
    { format: 'count', emptyText: 'Nothing to chart for this range.' },
);

const maxValue = computed(() =>
    Math.max(0, ...props.items.map((item) => item.value)),
);

function share(value: number): string {
    return `${maxValue.value === 0 ? 0 : (value / maxValue.value) * 100}%`;
}
</script>

<template>
    <!--
        Horizontal bars, because the categories here are names ("Ready for
        Pickup", "Utilities") that would collide under vertical columns.

        Each bar is an SVG rect sized by percentage -- no inline styles --
        with a 4px rounded end. A square 4px rect over its start keeps the
        baseline end flat. Every value is printed beside its bar, so the list
        itself is the accessible table.
    -->
    <figure class="flex flex-col gap-3">
        <p
            v-if="maxValue === 0"
            class="text-muted-foreground bg-muted/40 flex h-32 items-center justify-center rounded-lg text-sm"
        >
            {{ emptyText }}
        </p>
        <dl
            v-else
            class="grid grid-cols-[minmax(0,max-content)_minmax(3rem,1fr)_auto] items-center gap-x-3 gap-y-3 text-sm"
            :aria-label="label"
        >
            <div v-for="item in items" :key="item.label" class="contents">
                <dt class="text-muted-foreground truncate" :title="item.label">
                    {{ item.label }}
                </dt>
                <dd class="contents">
                    <svg class="h-3 w-full" aria-hidden="true">
                        <template v-if="item.value > 0">
                            <rect
                                :width="share(item.value)"
                                height="100%"
                                rx="4"
                                class="fill-chart-1"
                            />
                            <rect
                                width="4"
                                height="100%"
                                class="fill-chart-1"
                            />
                        </template>
                    </svg>
                    <span class="text-right font-semibold tabular-nums">
                        {{ chartValue(item.value, format) }}
                    </span>
                </dd>
            </div>
        </dl>
    </figure>
</template>

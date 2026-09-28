<script setup lang="ts">
import { useElementSize } from '@vueuse/core';
import { computed, ref, useTemplateRef } from 'vue';
import { chartValue, compactChartValue } from '@/lib/charts';
import type { ChartFormat, ChartSeries } from '@/lib/charts';

const props = withDefaults(
    defineProps<{
        /** Names the chart for screen readers and the data table caption. */
        label: string;
        labels: string[];
        series: ChartSeries[];
        format?: ChartFormat;
        emptyText?: string;
    }>(),
    { format: 'count', emptyText: 'Nothing to chart for this range.' },
);

/** Fixed series order -- a series keeps its colour however many are shown. */
const SERIES_FILLS = ['fill-chart-1', 'fill-chart-2'];
const SERIES_SWATCHES = ['bg-chart-1', 'bg-chart-2'];

const HEIGHT = 224;
const AXIS_WIDTH = 56;
const LABEL_BAND = 24;
const TOP_PAD = 8;
const PLOT_HEIGHT = HEIGHT - LABEL_BAND - TOP_PAD;
const BASELINE = TOP_PAD + PLOT_HEIGHT;

const plot = useTemplateRef<HTMLElement>('plot');
const { width } = useElementSize(plot);

const plotWidth = computed(() => Math.max(0, width.value - AXIS_WIDTH));
const slotWidth = computed(() => plotWidth.value / (props.labels.length || 1));

const maxValue = computed(() =>
    Math.max(0, ...props.series.flatMap((series) => series.values)),
);
const isEmpty = computed(() => maxValue.value === 0);

/**
 * Four gridline intervals on a 1-2-5 step, so every tick is a clean number.
 * Counts never step by less than one.
 */
const tickStep = computed(() => {
    const raw = (maxValue.value || 1) / 4;
    const magnitude = 10 ** Math.floor(Math.log10(raw));
    const step =
        [1, 2, 5, 10].find((multiple) => multiple * magnitude >= raw)! *
        magnitude;

    return props.format === 'count' ? Math.max(1, step) : step;
});
const ticks = computed(() => [0, 1, 2, 3, 4].map((i) => i * tickStep.value));
const axisMax = computed(() => tickStep.value * 4);

function y(value: number): number {
    return TOP_PAD + PLOT_HEIGHT * (1 - value / axisMax.value);
}

/** Bars are capped at 24px and 2px apart; the rest of the slot is air. */
const barWidth = computed(() => {
    const count = props.series.length;

    return Math.max(
        1,
        Math.min(24, (slotWidth.value * 0.7 - 2 * (count - 1)) / count),
    );
});

function barX(index: number, seriesIndex: number): number {
    const count = props.series.length;
    const groupWidth = count * barWidth.value + (count - 1) * 2;
    const center = AXIS_WIDTH + slotWidth.value * (index + 0.5);

    return center - groupWidth / 2 + seriesIndex * (barWidth.value + 2);
}

/** A column with a 4px rounded top, square where it meets the baseline. */
function barPath(x: number, value: number): string {
    const top = y(value);
    const w = barWidth.value;
    const r = Math.min(4, w / 2, BASELINE - top);

    return `M${x},${BASELINE}V${top + r}Q${x},${top} ${x + r},${top}H${x + w - r}Q${x + w},${top} ${x + w},${top + r}V${BASELINE}Z`;
}

/** Every nth label, so dates never overprint each other. */
const labelEvery = computed(() =>
    Math.ceil(
        props.labels.length / Math.max(1, Math.floor(plotWidth.value / 64)),
    ),
);

const activeIndex = ref<number | null>(null);

function onPointerMove(event: PointerEvent): void {
    const index = Math.floor((event.offsetX - AXIS_WIDTH) / slotWidth.value);

    activeIndex.value =
        index >= 0 && index < props.labels.length ? index : null;
}

function onKeydown(event: KeyboardEvent): void {
    const last = props.labels.length - 1;
    const current = activeIndex.value ?? -1;
    const next: Record<string, number> = {
        ArrowRight: Math.min(last, current + 1),
        ArrowLeft: Math.max(0, current - 1),
        Home: 0,
        End: last,
    };

    if (event.key in next) {
        event.preventDefault();
        activeIndex.value = next[event.key];
    }
}

function total(series: ChartSeries): number {
    return series.values.reduce((sum, value) => sum + value, 0);
}
</script>

<template>
    <!--
        One column group per label, drawn at the container's real pixel width
        so text and the 4px bar caps never stretch.

        Hovering or arrowing through the chart swaps the readout above it
        from the range totals to that one group's values -- a fixed readout
        rather than a floating tooltip, so it works the same on touch. The
        readout's swatches double as the legend, and the same figures sit in
        a screen-reader table underneath.
    -->
    <figure class="flex flex-col gap-3">
        <div
            v-if="!isEmpty"
            class="flex min-h-5 flex-wrap items-center gap-x-4 gap-y-1 text-sm"
            aria-live="polite"
        >
            <span class="text-muted-foreground">
                {{ activeIndex === null ? 'Total' : labels[activeIndex] }}
            </span>
            <span
                v-for="(item, seriesIndex) in series"
                :key="item.name"
                class="inline-flex items-center gap-1.5"
            >
                <span
                    aria-hidden="true"
                    class="size-2.5 rounded-sm"
                    :class="SERIES_SWATCHES[seriesIndex]"
                />
                <span v-if="series.length > 1" class="text-muted-foreground">
                    {{ item.name }}
                </span>
                <span class="font-semibold tabular-nums">
                    {{
                        chartValue(
                            activeIndex === null
                                ? total(item)
                                : item.values[activeIndex],
                            format,
                        )
                    }}
                </span>
            </span>
        </div>

        <div ref="plot" class="relative w-full">
            <p
                v-if="isEmpty"
                class="text-muted-foreground bg-muted/40 flex h-56 items-center justify-center rounded-lg text-sm"
            >
                {{ emptyText }}
            </p>
            <svg
                v-else-if="width > 0"
                :width="width"
                :height="HEIGHT"
                :viewBox="`0 0 ${width} ${HEIGHT}`"
                class="focus-visible:ring-ring/50 block rounded-md outline-none focus-visible:ring-[3px]"
                role="img"
                :aria-label="`${label}. Use the arrow keys to read each bar.`"
                tabindex="0"
                @pointermove="onPointerMove"
                @pointerleave="activeIndex = null"
                @keydown="onKeydown"
                @blur="activeIndex = null"
            >
                <g class="text-muted-foreground text-xs tabular-nums">
                    <template v-for="tick in ticks" :key="tick">
                        <line
                            :x1="AXIS_WIDTH"
                            :x2="width"
                            :y1="y(tick)"
                            :y2="y(tick)"
                            class="stroke-border"
                            stroke-width="1"
                        />
                        <text
                            :x="AXIS_WIDTH - 8"
                            :y="y(tick)"
                            text-anchor="end"
                            dominant-baseline="middle"
                            class="fill-current"
                        >
                            {{ compactChartValue(tick, format) }}
                        </text>
                    </template>
                </g>

                <rect
                    v-if="activeIndex !== null"
                    :x="AXIS_WIDTH + slotWidth * activeIndex"
                    :y="TOP_PAD"
                    :width="slotWidth"
                    :height="PLOT_HEIGHT"
                    class="fill-accent/60"
                />

                <template
                    v-for="(item, seriesIndex) in series"
                    :key="item.name"
                >
                    <template
                        v-for="(value, index) in item.values"
                        :key="index"
                    >
                        <path
                            v-if="value > 0"
                            :d="barPath(barX(index, seriesIndex), value)"
                            :class="SERIES_FILLS[seriesIndex]"
                        />
                    </template>
                </template>

                <g class="text-muted-foreground text-xs">
                    <template v-for="(text, index) in labels" :key="index">
                        <text
                            v-if="index % labelEvery === 0"
                            :x="AXIS_WIDTH + slotWidth * (index + 0.5)"
                            :y="HEIGHT - 6"
                            text-anchor="middle"
                            class="fill-current"
                        >
                            {{ text }}
                        </text>
                    </template>
                </g>
            </svg>
        </div>

        <table class="sr-only">
            <caption>
                {{
                    label
                }}
            </caption>
            <thead>
                <tr>
                    <th scope="col">Period</th>
                    <th v-for="item in series" :key="item.name" scope="col">
                        {{ item.name }}
                    </th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="(text, index) in labels" :key="index">
                    <th scope="row">{{ text }}</th>
                    <td v-for="item in series" :key="item.name">
                        {{ chartValue(item.values[index], format) }}
                    </td>
                </tr>
            </tbody>
        </table>
    </figure>
</template>

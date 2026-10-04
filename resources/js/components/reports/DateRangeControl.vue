<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useBusinessTime } from '@/composables/useBusinessTime';

const props = defineProps<{
    from: string;
    to: string;
    /** True while the range the user just picked is loading. */
    loading?: boolean;
}>();

const emit = defineEmits<{
    apply: [payload: { from: string; to: string }];
}>();

interface Preset {
    key: string;
    label: string;
    compute: () => { from: string; to: string };
}

const { calendarDay, shiftDay, formatDay } = useBusinessTime();

function todayRange(): { from: string; to: string } {
    const today = calendarDay();
    return { from: today, to: today };
}

/**
 * Today and the six days before it. A Monday-to-date "This Week" was just
 * today's date every Monday, so the button looked broken at the start of
 * each week.
 */
function lastSevenDaysRange(): { from: string; to: string } {
    const today = calendarDay();
    return { from: shiftDay(today, -6), to: today };
}

function thisMonthRange(): { from: string; to: string } {
    const today = calendarDay();
    return { from: `${today.slice(0, 7)}-01`, to: today };
}

function thisQuarterRange(): { from: string; to: string } {
    const today = calendarDay();
    const month = Number(today.slice(5, 7));
    const quarterStartMonth = Math.floor((month - 1) / 3) * 3 + 1;
    return {
        from: `${today.slice(0, 4)}-${String(quarterStartMonth).padStart(2, '0')}-01`,
        to: today,
    };
}

const presets: Preset[] = [
    { key: 'today', label: 'Today', compute: todayRange },
    { key: 'week', label: 'Last 7 Days', compute: lastSevenDaysRange },
    { key: 'month', label: 'This Month', compute: thisMonthRange },
    { key: 'quarter', label: 'This Quarter', compute: thisQuarterRange },
];

/**
 * The preset the user last clicked, or the one being loaded.
 *
 * Two presets can resolve to the same range -- on the 1st "This Month" is
 * just today -- so matching the range alone lit up "Today" after a click on
 * "This Month". The clicked preset wins while it still matches; a fresh page
 * load falls back to the first match.
 */
const chosenPresetKey = ref<string | null>(null);
const pendingKey = ref<string | null>(null);

function matches(preset: Preset): boolean {
    const range = preset.compute();
    return range.from === props.from && range.to === props.to;
}

const activePresetKey = computed<string | null>(() => {
    const chosen = presets.find(
        (preset) => preset.key === chosenPresetKey.value,
    );

    if (chosen && matches(chosen)) {
        return chosen.key;
    }

    return presets.find(matches)?.key ?? null;
});

const customFrom = ref(props.from);
const customTo = ref(props.to);
const manualCustom = ref(activePresetKey.value === null);
const errorMessage = ref('');

watch(
    () => [props.from, props.to] as const,
    ([from, to]) => {
        customFrom.value = from;
        customTo.value = to;
        errorMessage.value = '';

        if (activePresetKey.value !== null) {
            manualCustom.value = false;
        }
    },
);

const showCustomFields = computed(
    () => manualCustom.value || activePresetKey.value === null,
);

function variantFor(key: string): 'default' | 'outline' {
    return activePresetKey.value === key ? 'default' : 'outline';
}

function selectPreset(preset: Preset): void {
    manualCustom.value = false;
    errorMessage.value = '';
    chosenPresetKey.value = preset.key;
    pendingKey.value = preset.key;
    emit('apply', preset.compute());
}

watch(
    () => props.loading,
    (loading) => {
        if (!loading) {
            pendingKey.value = null;
        }
    },
);

const page = usePage();

/** The server's own date check, e.g. a range ending after today. */
const serverError = computed(
    () => page.props.errors?.from ?? page.props.errors?.to ?? '',
);

function selectCustom(): void {
    manualCustom.value = true;
    errorMessage.value = '';
}

function isValidIsoDate(value: string): boolean {
    if (!/^\d{4}-\d{2}-\d{2}$/.test(value)) {
        return false;
    }

    const date = new Date(`${value}T00:00:00Z`);
    return (
        !Number.isNaN(date.getTime()) &&
        date.toISOString().slice(0, 10) === value
    );
}

function applyCustomRange(): void {
    if (!isValidIsoDate(customFrom.value) || !isValidIsoDate(customTo.value)) {
        errorMessage.value = 'Enter a valid date.';
        return;
    }

    const today = calendarDay();

    if (customFrom.value > today || customTo.value > today) {
        errorMessage.value = 'Pick a date on or before today.';
        return;
    }

    if (customTo.value < customFrom.value) {
        errorMessage.value =
            "The end date can't be earlier than the start date.";
        return;
    }

    errorMessage.value = '';
    pendingKey.value = 'custom';
    emit('apply', { from: customFrom.value, to: customTo.value });
}

function formatDate(iso: string): string {
    return formatDay(iso, {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });
}

const rangeLabel = computed(() => {
    if (props.from === props.to) {
        return formatDate(props.from);
    }

    return `${formatDate(props.from)} – ${formatDate(props.to)}`;
});
</script>

<template>
    <div class="flex flex-col gap-2">
        <div class="flex flex-wrap gap-2">
            <Button
                v-for="preset in presets"
                :key="preset.key"
                type="button"
                :variant="variantFor(preset.key)"
                :disabled="loading"
                :aria-pressed="activePresetKey === preset.key"
                @click="selectPreset(preset)"
            >
                <Spinner v-if="loading && pendingKey === preset.key" />
                {{ preset.label }}
            </Button>
            <Button
                type="button"
                variant="outline"
                :disabled="loading"
                @click="selectCustom"
            >
                Custom
            </Button>
        </div>

        <div v-if="showCustomFields" class="flex flex-wrap items-end gap-2">
            <div class="flex flex-col gap-1">
                <Label for="date-range-from">From</Label>
                <Input
                    id="date-range-from"
                    v-model="customFrom"
                    type="date"
                    class="w-40"
                />
            </div>

            <div class="flex flex-col gap-1">
                <Label for="date-range-to">To</Label>
                <Input
                    id="date-range-to"
                    v-model="customTo"
                    type="date"
                    class="w-40"
                />
            </div>

            <Button type="button" :disabled="loading" @click="applyCustomRange">
                <Spinner v-if="loading && pendingKey === 'custom'" />
                Apply Range
            </Button>
        </div>

        <p
            v-if="errorMessage || serverError"
            class="text-destructive text-sm"
            role="alert"
        >
            {{ errorMessage || serverError }}
        </p>

        <p class="text-muted-foreground text-sm">Showing {{ rangeLabel }}</p>
    </div>
</template>

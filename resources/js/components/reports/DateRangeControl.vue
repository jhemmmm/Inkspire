<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

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

function pad(value: number): string {
    return String(value).padStart(2, '0');
}

function toIsoDate(date: Date): string {
    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
}

function todayRange(): { from: string; to: string } {
    const today = toIsoDate(new Date());
    return { from: today, to: today };
}

/**
 * Today and the six days before it. A Monday-to-date "This Week" was just
 * today's date every Monday, so the button looked broken at the start of
 * each week.
 */
function lastSevenDaysRange(): { from: string; to: string } {
    const now = new Date();
    const weekAgo = new Date(now);
    weekAgo.setDate(now.getDate() - 6);
    return { from: toIsoDate(weekAgo), to: toIsoDate(now) };
}

function thisMonthRange(): { from: string; to: string } {
    const now = new Date();
    const firstOfMonth = new Date(now.getFullYear(), now.getMonth(), 1);
    return { from: toIsoDate(firstOfMonth), to: toIsoDate(now) };
}

function thisQuarterRange(): { from: string; to: string } {
    const now = new Date();
    const quarterStartMonth = Math.floor(now.getMonth() / 3) * 3;
    const firstOfQuarter = new Date(now.getFullYear(), quarterStartMonth, 1);
    return { from: toIsoDate(firstOfQuarter), to: toIsoDate(now) };
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

    return !Number.isNaN(new Date(value).getTime());
}

function applyCustomRange(): void {
    if (!isValidIsoDate(customFrom.value) || !isValidIsoDate(customTo.value)) {
        errorMessage.value = 'Enter a valid date.';
        return;
    }

    const today = toIsoDate(new Date());

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
    const [year, month, day] = iso.split('-').map(Number);
    return new Date(year, month - 1, day).toLocaleDateString('en-PH', {
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

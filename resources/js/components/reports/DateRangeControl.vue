<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

const props = defineProps<{
    from: string;
    to: string;
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

function thisWeekRange(): { from: string; to: string } {
    const now = new Date();
    const day = now.getDay();
    const diffToMonday = day === 0 ? 6 : day - 1;
    const monday = new Date(now);
    monday.setDate(now.getDate() - diffToMonday);
    return { from: toIsoDate(monday), to: toIsoDate(now) };
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
    { key: 'week', label: 'This Week', compute: thisWeekRange },
    { key: 'month', label: 'This Month', compute: thisMonthRange },
    { key: 'quarter', label: 'This Quarter', compute: thisQuarterRange },
];

const activePresetKey = computed<string | null>(() => {
    const match = presets.find((preset) => {
        const range = preset.compute();
        return range.from === props.from && range.to === props.to;
    });

    return match?.key ?? null;
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
    emit('apply', preset.compute());
}

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
                @click="selectPreset(preset)"
            >
                {{ preset.label }}
            </Button>
            <Button type="button" variant="outline" @click="selectCustom">
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

            <Button type="button" @click="applyCustomRange">Apply Range</Button>
        </div>

        <p v-if="errorMessage" class="text-destructive text-sm">
            {{ errorMessage }}
        </p>

        <p class="text-muted-foreground text-sm">Showing {{ rangeLabel }}</p>
    </div>
</template>

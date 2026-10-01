<script setup lang="ts">
import { computed } from 'vue';
import InputError from '@/components/InputError.vue';
import SearchableSelect, {
    type SearchableOption,
} from '@/components/SearchableSelect.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    isSqFtUnit,
    money,
    parseNumber,
    parseQuantity,
    quoteLineAmount,
    round2,
    rushFee,
} from '@/lib/jobOrders';

export interface JobOrderPriceRow {
    pricing_entry_id: string;
    description: string;
    print_size: string;
    width_ft: string;
    height_ft: string;
    quantity: string;
    quoted_amount: string;
    quoted_amount_overridden: boolean;
    is_rush: boolean;
}

interface PricingEntryOption {
    id: number;
    name: string;
    base_price: string;
    unit: string | null;
}

const props = withDefaults(
    defineProps<{
        pricingEntries: PricingEntryOption[];
        printSizeOptions: SearchableOption[];
        printSizeDimensions: Record<
            string,
            { width_inches: number | null; height_inches: number | null }
        >;
        row: JobOrderPriceRow;
        rushFeePercentage: number;
        idPrefix: string;
        /** Render hidden inputs so SearchableSelect values submit in an uncontrolled `<Form>`. */
        nativeNames?: boolean;
        /** Bare field-name keyed — the caller slices/prefixes it already. */
        errors?: Record<string, string | undefined>;
    }>(),
    {
        nativeNames: false,
        errors: () => ({}),
    },
);

const serviceOptions = computed<SearchableOption[]>(() =>
    props.pricingEntries.map((entry) => ({
        value: String(entry.id),
        label: entry.name,
        hint: servicePriceLabel(entry),
    })),
);

function servicePriceLabel(entry: PricingEntryOption): string {
    const price = money(Number(entry.base_price));

    return entry.unit ? `${price} / ${entry.unit}` : price;
}

const selectedEntry = computed(() =>
    props.pricingEntries.find(
        (entry) => String(entry.id) === props.row.pricing_entry_id,
    ),
);

const isSqFt = computed(() => isSqFtUnit(selectedEntry.value?.unit));

/**
 * Selecting a service sets the catalog id *and* mirrors its name into
 * `description` — the id is what pricing reads, the name is what every
 * existing screen already displays. Changing the service snaps the row
 * back to the computed price: an override tied to the old service's rate
 * would silently misprice the new one.
 */
function selectService(value: unknown): void {
    const id = String(value ?? '');
    props.row.pricing_entry_id = id;
    props.row.description =
        props.pricingEntries.find((entry) => String(entry.id) === id)?.name ??
        '';
    props.row.quoted_amount_overridden = false;
    props.row.quoted_amount = '';
}

/**
 * A print-size preset with recorded dimensions fills Width/Height (ft).
 * Presets are minimums, not fixed values, so the fields stay editable —
 * a preset with no recorded dimensions (e.g. "Custom Size") leaves the
 * current values untouched rather than clearing them.
 */
function selectPrintSize(value: unknown): void {
    const label = String(value ?? '');
    props.row.print_size = label;

    const dimensions = props.printSizeDimensions[label];

    if (
        !dimensions ||
        dimensions.width_inches === null ||
        dimensions.height_inches === null
    ) {
        return;
    }

    props.row.width_ft = round2(dimensions.width_inches / 12).toString();
    props.row.height_ft = round2(dimensions.height_inches / 12).toString();
}

const widthFt = computed(() => parseNumber(props.row.width_ft));
const heightFt = computed(() => parseNumber(props.row.height_ft));
const quantity = computed(() => parseQuantity(props.row.quantity));

const computedAmount = computed(() =>
    quoteLineAmount(
        selectedEntry.value,
        widthFt.value,
        heightFt.value,
        quantity.value,
    ),
);

const rushHint = computed(() => {
    if (!props.row.is_rush || computedAmount.value === null) {
        return null;
    }

    return rushFee(computedAmount.value, props.rushFeePercentage);
});

const priceFormula = computed(() => {
    if (!selectedEntry.value) {
        return null;
    }

    const base = money(Number(selectedEntry.value.base_price));

    if (isSqFt.value) {
        if (widthFt.value === null || heightFt.value === null) {
            return `${base} per sq ft — enter width and height to see the total`;
        }

        return `${base} × ${widthFt.value} ft × ${heightFt.value} ft × ${quantity.value} = ${money(computedAmount.value)}`;
    }

    return `${base} × ${quantity.value} = ${money(computedAmount.value)}`;
});

function startAdjusting(): void {
    props.row.quoted_amount_overridden = true;
    props.row.quoted_amount = String(computedAmount.value ?? 0);
}

function useComputed(): void {
    props.row.quoted_amount_overridden = false;
    props.row.quoted_amount = '';
}
</script>

<template>
    <div class="grid gap-6">
        <div class="grid gap-2">
            <Label :for="`${idPrefix}-service`">Product / Service</Label>
            <SearchableSelect
                :id="`${idPrefix}-service`"
                :model-value="row.pricing_entry_id"
                :options="serviceOptions"
                placeholder="Search the price list…"
                empty-text="No service matches that search."
                @update:model-value="selectService"
            />
            <input
                v-if="nativeNames"
                type="hidden"
                name="pricing_entry_id"
                :value="row.pricing_entry_id"
            />
            <input
                v-if="nativeNames"
                type="hidden"
                name="description"
                :value="row.description"
            />
            <InputError
                :message="errors.pricing_entry_id ?? errors.description"
            />
        </div>

        <div class="grid gap-2">
            <Label :for="`${idPrefix}-print-size`">Print Size</Label>
            <SearchableSelect
                :id="`${idPrefix}-print-size`"
                :model-value="row.print_size"
                :options="printSizeOptions"
                placeholder="Search print sizes…"
                empty-text="No print size matches that search."
                @update:model-value="selectPrintSize"
            />
            <input
                v-if="nativeNames"
                type="hidden"
                name="print_size"
                :value="row.print_size"
            />
            <InputError :message="errors.print_size" />
        </div>

        <div v-if="isSqFt" class="grid gap-4 @2xl:grid-cols-2">
            <div class="grid gap-2">
                <Label :for="`${idPrefix}-width-ft`">Width (ft)</Label>
                <Input
                    :id="`${idPrefix}-width-ft`"
                    v-model="row.width_ft"
                    type="number"
                    min="0.01"
                    step="0.01"
                    :name="nativeNames ? 'width_ft' : undefined"
                    placeholder="e.g. 3"
                />
                <InputError :message="errors.width_ft" />
            </div>
            <div class="grid gap-2">
                <Label :for="`${idPrefix}-height-ft`">Height (ft)</Label>
                <Input
                    :id="`${idPrefix}-height-ft`"
                    v-model="row.height_ft"
                    type="number"
                    min="0.01"
                    step="0.01"
                    :name="nativeNames ? 'height_ft' : undefined"
                    placeholder="e.g. 5"
                />
                <InputError :message="errors.height_ft" />
            </div>
        </div>

        <div class="grid gap-2">
            <Label :for="`${idPrefix}-quantity`">Quantity</Label>
            <Input
                :id="`${idPrefix}-quantity`"
                v-model="row.quantity"
                type="number"
                min="1"
                :name="nativeNames ? 'quantity' : undefined"
                placeholder="Quantity"
            />
            <InputError :message="errors.quantity" />
        </div>

        <div class="border-border bg-muted/30 rounded-xl border p-4">
            <p
                v-if="!selectedEntry"
                class="text-muted-foreground text-sm"
                :data-test="`${idPrefix}-price-empty`"
            >
                Pick a product to see the price
            </p>
            <template v-else>
                <p class="text-muted-foreground text-sm tabular-nums">
                    {{ priceFormula }}
                </p>
                <p
                    v-if="rushHint !== null"
                    class="text-muted-foreground text-sm tabular-nums"
                >
                    + rush {{ money(rushHint) }}
                </p>

                <div class="mt-3 flex items-center justify-between gap-3">
                    <template v-if="row.quoted_amount_overridden">
                        <div class="grid gap-2">
                            <Input
                                :id="`${idPrefix}-quoted-amount`"
                                v-model="row.quoted_amount"
                                type="number"
                                min="0"
                                step="0.01"
                                class="max-w-40 text-lg font-semibold tabular-nums"
                                :name="
                                    nativeNames ? 'quoted_amount' : undefined
                                "
                                :data-test="`${idPrefix}-quoted-amount-input`"
                            />
                            <InputError :message="errors.quoted_amount" />
                        </div>
                        <button
                            type="button"
                            class="text-primary text-sm font-medium underline-offset-4 hover:underline"
                            :data-test="`${idPrefix}-use-computed-button`"
                            @click="useComputed"
                        >
                            Use computed
                        </button>
                    </template>
                    <template v-else>
                        <p
                            class="text-lg font-semibold tabular-nums"
                            :data-test="`${idPrefix}-total`"
                        >
                            {{ money(computedAmount) }}
                        </p>
                        <button
                            type="button"
                            class="text-primary text-sm font-medium underline-offset-4 hover:underline"
                            :data-test="`${idPrefix}-adjust-button`"
                            @click="startAdjusting"
                        >
                            Adjust
                        </button>
                    </template>
                </div>
            </template>
        </div>
    </div>
</template>

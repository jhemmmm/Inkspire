<script lang="ts">
import { uuid } from '@/lib/uuid';

export interface JobOrderRow {
    description: string;
    pricing_entry_id: string;
    client_notes: string;
    type: 'type_a' | 'type_b';
    print_size: string;
    width_ft: string;
    height_ft: string;
    quantity: string;
    quoted_amount: string;
    quoted_amount_overridden: boolean;
    deadline: string;
    is_rush: boolean;
    file: File | null;
    _key: string;
}

export function emptyJobOrderRow(): JobOrderRow {
    return {
        description: '',
        pricing_entry_id: '',
        client_notes: '',
        type: 'type_a',
        print_size: '',
        width_ft: '',
        height_ft: '',
        quantity: '',
        quoted_amount: '',
        quoted_amount_overridden: false,
        deadline: '',
        is_rush: false,
        file: null,
        _key: uuid(),
    };
}
</script>

<script setup lang="ts">
import {
    CalendarDays,
    CloudUpload,
    FileText,
    PencilRuler,
    Printer,
    Settings,
    X,
    Zap,
} from '@lucide/vue';
import { computed } from 'vue';
import InputError from '@/components/InputError.vue';
import JobOrderPriceFields from '@/components/JobOrderPriceFields.vue';
import { type SearchableOption } from '@/components/SearchableSelect.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';

interface PricingEntry {
    id: number;
    name: string;
    base_price?: string;
    unit: string | null;
}

const props = withDefaults(
    defineProps<{
        row: JobOrderRow;
        index: number;
        /** Bare field-name keyed — the caller slices the row prefix off. */
        errors?: Record<string, string | undefined>;
        pricingEntries: PricingEntry[];
        specificationOptions: Record<string, string[]>;
        printSizeDimensions: Record<
            string,
            { width_inches: number | null; height_inches: number | null }
        >;
        rushFeePercentage: number;
        acceptedFileFormats: string[];
        removable?: boolean;
        /** `customer` uses plain wording and never shows a price. */
        audience?: 'staff' | 'customer';
    }>(),
    {
        errors: () => ({}),
        removable: false,
        audience: 'staff',
    },
);

const emit = defineEmits<{
    remove: [];
}>();

const isCustomer = computed(() => props.audience === 'customer');

const printSizeOptions = computed<SearchableOption[]>(() =>
    (props.specificationOptions.print_size ?? []).map((size) => ({
        value: size,
        label: size,
    })),
);

// Today, in the browser's own timezone -- `toISOString()` would render the
// UTC date and let a Manila-evening walk-in pick "today" only to have the
// server's `after_or_equal:today` reject it.
const earliestDeadline = computed(() => {
    const now = new Date();

    return [
        now.getFullYear(),
        String(now.getMonth() + 1).padStart(2, '0'),
        String(now.getDate()).padStart(2, '0'),
    ].join('-');
});

/**
 * Built from the same `accepted_file_formats` setting the server's Type A file
 * check reads, so the picker never offers a format that will be rejected.
 */
const acceptedFileTypes = computed(() =>
    props.acceptedFileFormats.map((format) => `.${format}`).join(','),
);
const acceptedFileHint = computed(
    () =>
        `Accepted: ${props.acceptedFileFormats.map((format) => format.toUpperCase()).join(', ')}`,
);

const jobOrderTypeOptions = computed(() =>
    isCustomer.value
        ? [
              {
                  value: 'type_a' as const,
                  icon: Printer,
                  title: 'I have a print-ready file',
                  badge: null,
                  blurb: 'Upload your design and we print it as it is.',
              },
              {
                  value: 'type_b' as const,
                  icon: PencilRuler,
                  title: 'I need a design',
                  badge: null,
                  blurb: 'Tell us what you want and an artist will make the layout with you.',
              },
          ]
        : [
              {
                  value: 'type_a' as const,
                  icon: Printer,
                  title: 'Type A — Print Only',
                  badge: 'READY-MADE',
                  blurb: 'Has own design file. For printing only — no artist needed.',
              },
              {
                  value: 'type_b' as const,
                  icon: PencilRuler,
                  title: 'Type B — Consultation',
                  badge: 'NO LAYOUT',
                  blurb: 'No design yet. Needs artist consultation and layout creation.',
              },
          ],
);

const rushHelper = computed(() =>
    isCustomer.value
        ? 'We will work on this one first. Rush orders may cost extra; we will confirm the amount with you.'
        : 'Prioritised in production. The Cashier decides whether the rush fee is charged.',
);

const notesPlaceholder = computed(() => {
    if (props.row.type === 'type_b') {
        return 'What the customer wants: colours, wording, references, questions they asked…';
    }

    return isCustomer.value
        ? 'Anything we should know about this print.'
        : 'Anything the customer mentioned about this print.';
});

const notesHelper = computed(() => {
    if (props.row.type === 'type_b') {
        return isCustomer.value
            ? 'Your artist reads this before they contact you.'
            : 'The artist sees this as their brief before the consultation.';
    }

    return isCustomer.value
        ? 'Sent along with your order.'
        : 'Passed along with the job order.';
});

const fileHelper = computed(() =>
    isCustomer.value
        ? 'We check the file against the print size above. If it is too low-resolution to print sharply, an artist will help improve it first.'
        : 'Checked against the print size above. If it is too low-resolution to print sharply at that size, an artist picks it up to improve it first.',
);

function onFileChange(event: Event): void {
    props.row.file = (event.target as HTMLInputElement).files?.[0] ?? null;
}

function onFileDrop(event: DragEvent): void {
    props.row.file = event.dataTransfer?.files?.[0] ?? null;
}

function formatFileSize(bytes: number): string {
    const megabytes = bytes / 1024 / 1024;

    return megabytes < 1
        ? `${Math.max(1, Math.round(bytes / 1024))} KB`
        : `${megabytes.toFixed(1)} MB`;
}

function selectJobOrderType(value: unknown): void {
    props.row.type = value === 'type_b' ? 'type_b' : 'type_a';
    if (props.row.type !== 'type_a') {
        props.row.file = null;
    }
}
</script>

<template>
    <Card>
        <CardHeader :icon="FileText">
            <div class="flex items-center justify-between gap-2">
                <CardTitle>Job Order {{ index + 1 }}</CardTitle>
                <Button
                    v-if="removable"
                    type="button"
                    variant="outline"
                    size="icon"
                    :data-test="`remove-job-order-${index}-button`"
                    @click="emit('remove')"
                >
                    <X class="size-4" />
                    <span class="sr-only">Remove job order</span>
                </Button>
            </div>
        </CardHeader>
        <CardContent class="grid gap-6">
            <fieldset class="grid gap-2">
                <legend class="sr-only">Job Order Type</legend>
                <p class="text-sm font-medium">Job Order Type</p>
                <div class="grid gap-4 @2xl:grid-cols-2">
                    <label
                        v-for="option in jobOrderTypeOptions"
                        :key="option.value"
                        class="border-border bg-card has-[:checked]:border-primary has-[:checked]:bg-accent/40 has-[:focus-visible]:ring-ring/50 relative flex cursor-pointer gap-3 rounded-xl border-2 p-4 transition-colors has-[:focus-visible]:ring-[3px]"
                        :data-test="`job-order-${index}-${option.value}-card`"
                    >
                        <input
                            type="radio"
                            class="peer sr-only"
                            :name="`job-order-type-${row._key}`"
                            :value="option.value"
                            :checked="row.type === option.value"
                            @change="selectJobOrderType(option.value)"
                        />
                        <span
                            class="bg-muted text-muted-foreground peer-checked:bg-primary peer-checked:text-primary-foreground flex size-9 shrink-0 items-center justify-center rounded-lg transition-colors"
                        >
                            <component :is="option.icon" class="size-[18px]" />
                        </span>
                        <span class="flex min-w-0 flex-1 flex-col gap-1">
                            <span
                                class="flex items-start justify-between gap-2"
                            >
                                <span class="font-semibold">
                                    {{ option.title }}
                                </span>
                                <Badge
                                    v-if="option.badge"
                                    variant="secondary"
                                    class="shrink-0"
                                >
                                    {{ option.badge }}
                                </Badge>
                            </span>
                            <span class="text-muted-foreground text-sm">
                                {{ option.blurb }}
                            </span>
                        </span>
                    </label>
                </div>
                <InputError :message="errors.type" />
            </fieldset>

            <section class="border-border overflow-hidden rounded-xl border">
                <div
                    class="bg-muted/40 border-border flex items-center gap-3 border-b px-6 py-4"
                >
                    <span
                        class="bg-accent text-accent-foreground flex size-9 shrink-0 items-center justify-center rounded-lg"
                    >
                        <Settings class="size-[18px]" />
                    </span>
                    <h3 class="font-semibold">Print Specifications</h3>
                </div>

                <div class="grid gap-6 p-6 @2xl:grid-cols-2">
                    <div class="@2xl:col-span-2">
                        <JobOrderPriceFields
                            :row="row"
                            :pricing-entries="pricingEntries"
                            :print-size-options="printSizeOptions"
                            :print-size-dimensions="printSizeDimensions"
                            :rush-fee-percentage="rushFeePercentage"
                            :id-prefix="`job-order-${index}`"
                            :errors="errors"
                            :show-prices="!isCustomer"
                        />
                    </div>

                    <div class="grid gap-2">
                        <Label :for="`job-order-deadline-${index}`">
                            Deadline
                        </Label>
                        <div class="relative">
                            <CalendarDays
                                class="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2"
                            />
                            <Input
                                :id="`job-order-deadline-${index}`"
                                v-model="row.deadline"
                                type="date"
                                :min="earliestDeadline"
                                class="pl-9"
                            />
                        </div>
                        <InputError :message="errors.deadline" />
                    </div>

                    <div class="grid gap-2 @2xl:col-span-2">
                        <div class="flex items-center gap-3">
                            <Switch
                                :id="`job-order-rush-${index}`"
                                v-model="row.is_rush"
                                :data-test="`job-order-${index}-rush-switch`"
                            />
                            <Label
                                :for="`job-order-rush-${index}`"
                                class="flex items-center gap-2"
                            >
                                <Zap class="size-4" />
                                Rush Print
                            </Label>
                        </div>
                        <p class="text-muted-foreground text-sm">
                            {{ rushHelper }}
                        </p>
                        <InputError :message="errors.is_rush" />
                    </div>

                    <div class="grid gap-2 @2xl:col-span-2">
                        <Label :for="`job-order-notes-${index}`">
                            {{
                                row.type === 'type_b'
                                    ? isCustomer
                                        ? 'Your Instructions'
                                        : 'Client Instructions'
                                    : 'Notes'
                            }}
                            <span class="text-muted-foreground font-normal">
                                (optional)
                            </span>
                        </Label>
                        <Textarea
                            :id="`job-order-notes-${index}`"
                            v-model="row.client_notes"
                            rows="4"
                            :placeholder="notesPlaceholder"
                        />
                        <p class="text-muted-foreground text-sm">
                            {{ notesHelper }}
                        </p>
                        <InputError :message="errors.client_notes" />
                    </div>

                    <div
                        v-if="row.type === 'type_a'"
                        class="grid gap-2 @2xl:col-span-2"
                    >
                        <Label :for="`job-order-file-${index}`">
                            {{ isCustomer ? 'Your File' : 'Source File' }}
                        </Label>
                        <label
                            class="border-border bg-muted/30 hover:border-primary hover:bg-accent/40 has-[:focus-visible]:border-ring has-[:focus-visible]:ring-ring/50 flex cursor-pointer flex-col items-center gap-2 rounded-xl border-2 border-dashed px-6 py-10 text-center transition-colors has-[:focus-visible]:ring-[3px]"
                            :data-test="`job-order-${index}-dropzone`"
                            @dragover.prevent
                            @drop.prevent="onFileDrop"
                        >
                            <input
                                :id="`job-order-file-${index}`"
                                type="file"
                                class="sr-only"
                                :accept="acceptedFileTypes"
                                @change="onFileChange"
                            />
                            <CloudUpload class="text-muted-foreground size-7" />
                            <span class="font-semibold">
                                {{
                                    row.file
                                        ? row.file.name
                                        : 'Drop a file here or click to browse'
                                }}
                            </span>
                            <span class="text-muted-foreground text-sm">
                                {{
                                    row.file
                                        ? formatFileSize(row.file.size)
                                        : acceptedFileHint
                                }}
                            </span>
                            <span
                                class="text-muted-foreground max-w-prose text-xs"
                            >
                                {{ fileHelper }}
                            </span>
                        </label>
                        <InputError :message="errors.file" />
                    </div>
                </div>
            </section>
        </CardContent>
    </Card>
</template>

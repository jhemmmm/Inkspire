<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { Check, Pencil, Plus, Ruler, Trash2, X } from '@lucide/vue';
import { computed, ref } from 'vue';
import type { ComputedRef } from 'vue';
import SpecificationOptionController from '@/actions/App/Http/Controllers/Admin/SpecificationOptionController';
import EmptyState from '@/components/EmptyState.vue';
import InputError from '@/components/InputError.vue';
import PageContainer from '@/components/PageContainer.vue';
import PageHeader from '@/components/PageHeader.vue';
import TableFilterBar from '@/components/TableFilterBar.vue';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
    AlertDialogTrigger,
} from '@/components/ui/alert-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import {
    Table,
    TableBody,
    TableCell,
    TableEmpty,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useTableFilter } from '@/composables/useTableFilter';
import { adminNavItems } from '@/config/nav/admin';
import { index as specificationsIndex } from '@/routes/admin/specifications';

interface SpecificationCategory {
    value: string;
    label: string;
}

interface SpecificationOption {
    id: number;
    category: string;
    label: string;
    width_inches: number | null;
    height_inches: number | null;
    is_active: boolean;
    sort_order: number;
}

const props = defineProps<{
    categories: SpecificationCategory[];
    options: Record<string, SpecificationOption[]>;
}>();

defineOptions({
    layout: {
        navItems: adminNavItems,
        breadcrumbs: [
            {
                title: 'Print Specifications',
                href: specificationsIndex(),
            },
        ],
    },
});

const CATEGORY_ICONS: Record<string, typeof Ruler> = {
    print_size: Ruler,
};

function categoryIcon(category: string): typeof Ruler {
    return CATEGORY_ICONS[category] ?? Ruler;
}

function optionsFor(category: string): SpecificationOption[] {
    return props.options[category] ?? [];
}

const ALL = 'all';

const searchTerm = ref('');
const statusFilter = ref(ALL);

const statusFilterOptions = [
    { value: ALL, label: 'All statuses' },
    { value: 'active', label: 'Active' },
    { value: 'retired', label: 'Retired' },
];

/**
 * One useTableFilter instance per category, all sharing the same
 * `searchTerm` ref (one search box, D3's "share one search box between two
 * lists" clause) and the same status predicate. Built once at setup time,
 * mirroring this file's existing per-category `createForms` object.
 */
const filteredByCategory: Record<
    string,
    ComputedRef<SpecificationOption[]>
> = Object.fromEntries(
    props.categories.map((category) => [
        category.value,
        useTableFilter(
            () => optionsFor(category.value),
            (option) => [option.label],
            {
                searchTerm,
                filters: [
                    (option) =>
                        statusFilter.value === ALL ||
                        option.is_active === (statusFilter.value === 'active'),
                ],
            },
        ).filtered,
    ]),
);

function filteredOptionsFor(category: string): SpecificationOption[] {
    return filteredByCategory[category]?.value ?? [];
}

const totalOptionsCount = computed(() =>
    props.categories.reduce(
        (sum, category) => sum + optionsFor(category.value).length,
        0,
    ),
);

const shownOptionsCount = computed(() =>
    props.categories.reduce(
        (sum, category) => sum + filteredOptionsFor(category.value).length,
        0,
    ),
);

const filtersActive = computed(
    () => searchTerm.value.trim() !== '' || statusFilter.value !== ALL,
);

function clearFilters(): void {
    searchTerm.value = '';
    statusFilter.value = ALL;
}

/**
 * Print sizes without recorded dimensions are not an error — "Custom Size"
 * legitimately has none — but they do opt out of the intake file check, so
 * say so rather than showing an empty cell.
 */
function printedSizeLabel(option: SpecificationOption): string {
    if (option.width_inches === null || option.height_inches === null) {
        return 'Not set — file check skipped';
    }

    return `${option.width_inches}″ × ${option.height_inches}″`;
}

// One create form per category, so a validation error in one category
// cannot paint a red message under another category's input.
const createForms = Object.fromEntries(
    props.categories.map((category) => [
        category.value,
        useForm({
            category: category.value,
            label: '',
            width_inches: '',
            height_inches: '',
        }),
    ]),
);

function addOption(category: string): void {
    createForms[category].post(SpecificationOptionController.store().url, {
        preserveScroll: true,
        onSuccess: () =>
            createForms[category].reset(
                'label',
                'width_inches',
                'height_inches',
            ),
    });
}

const editingId = ref<number | null>(null);
const editingLabel = ref('');

function startEditing(option: SpecificationOption): void {
    editingId.value = option.id;
    editingLabel.value = option.label;
}

function cancelEditing(): void {
    editingId.value = null;
    editingLabel.value = '';
}

function saveLabel(option: SpecificationOption): void {
    router.patch(
        SpecificationOptionController.update(option.id).url,
        {
            label: editingLabel.value,
            is_active: option.is_active,
            width_inches: option.width_inches,
            height_inches: option.height_inches,
        },
        { preserveScroll: true, onSuccess: cancelEditing },
    );
}

/**
 * Retiring an option drops it from the intake form's selects while leaving
 * every job order that already carries the label untouched — the reversible
 * counterpart to deleting it outright.
 */
function toggleActive(option: SpecificationOption, isActive: boolean): void {
    router.patch(
        SpecificationOptionController.update(option.id).url,
        {
            label: option.label,
            is_active: isActive,
            width_inches: option.width_inches,
            height_inches: option.height_inches,
        },
        { preserveScroll: true },
    );
}

function deleteOption(option: SpecificationOption): void {
    router.delete(SpecificationOptionController.destroy(option.id).url, {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head title="Print Specifications" />

    <PageContainer>
        <PageHeader
            title="Print Specifications"
            description="The lists Frontline Staff pick from when creating a job order. Retiring an option hides it from new job orders without touching the ones already printed at that spec."
        />

        <TableFilterBar
            v-model:search="searchTerm"
            search-label="Search specification options"
            search-placeholder="Search by option name"
            :shown="shownOptionsCount"
            :total="totalOptionsCount"
            :active="filtersActive"
            @clear="clearFilters"
        >
            <div class="flex min-w-0 flex-col gap-2 @lg:w-48">
                <Label for="specification-status-filter">Status</Label>
                <Select v-model="statusFilter">
                    <SelectTrigger
                        id="specification-status-filter"
                        class="w-full"
                    >
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="option in statusFilterOptions"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>
        </TableFilterBar>

        <Card v-for="category in categories" :key="category.value">
            <CardHeader :icon="categoryIcon(category.value)">
                <CardTitle>
                    {{ category.label }}
                    <span
                        class="text-muted-foreground text-sm font-normal tabular-nums"
                    >
                        ({{ filteredOptionsFor(category.value).length }} of
                        {{ optionsFor(category.value).length }})
                    </span>
                </CardTitle>
            </CardHeader>
            <CardContent class="flex flex-col gap-6 p-0">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Option</TableHead>
                            <TableHead v-if="category.value === 'print_size'">
                                Printed size
                            </TableHead>
                            <TableHead>Offered at intake</TableHead>
                            <TableHead class="text-right">Actions</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableEmpty
                            v-if="optionsFor(category.value).length === 0"
                            :colspan="category.value === 'print_size' ? 4 : 3"
                        >
                            <div class="flex flex-col items-center gap-1">
                                <p class="font-semibold">
                                    No {{ category.label.toLowerCase() }} yet
                                </p>
                                <p class="text-muted-foreground">
                                    Add one below to make it selectable at
                                    intake.
                                </p>
                            </div>
                        </TableEmpty>
                        <TableEmpty
                            v-else-if="
                                filteredOptionsFor(category.value).length === 0
                            "
                            :colspan="category.value === 'print_size' ? 4 : 3"
                        >
                            <EmptyState
                                title="No matches"
                                :description="`No ${category.label.toLowerCase()} match the current search or status filter.`"
                            >
                                <template #actions>
                                    <Button
                                        type="button"
                                        variant="secondary"
                                        data-test="clear-specification-filters-button"
                                        @click="clearFilters"
                                    >
                                        Clear filters
                                    </Button>
                                </template>
                            </EmptyState>
                        </TableEmpty>
                        <TableRow
                            v-for="option in filteredOptionsFor(category.value)"
                            v-else
                            :key="option.id"
                            :data-test="`specification-${option.id}-row`"
                        >
                            <TableCell>
                                <div
                                    v-if="editingId === option.id"
                                    class="flex items-center gap-2"
                                >
                                    <Input
                                        v-model="editingLabel"
                                        class="max-w-xs"
                                        :aria-label="`Rename ${option.label}`"
                                        @keyup.enter="saveLabel(option)"
                                        @keyup.esc="cancelEditing"
                                    />
                                    <Button
                                        size="icon"
                                        variant="ghost"
                                        :data-test="`save-specification-${option.id}-button`"
                                        @click="saveLabel(option)"
                                    >
                                        <Check class="size-4" />
                                        <span class="sr-only">Save</span>
                                    </Button>
                                    <Button
                                        size="icon"
                                        variant="ghost"
                                        @click="cancelEditing"
                                    >
                                        <X class="size-4" />
                                        <span class="sr-only">Cancel</span>
                                    </Button>
                                </div>
                                <span v-else>{{ option.label }}</span>
                            </TableCell>
                            <TableCell
                                v-if="category.value === 'print_size'"
                                class="text-muted-foreground tabular-nums"
                            >
                                {{ printedSizeLabel(option) }}
                            </TableCell>
                            <TableCell>
                                <div class="flex items-center gap-3">
                                    <Switch
                                        :model-value="option.is_active"
                                        :aria-label="`Offer ${option.label} at intake`"
                                        :data-test="`toggle-specification-${option.id}`"
                                        @update:model-value="
                                            (value: boolean) =>
                                                toggleActive(option, value)
                                        "
                                    />
                                    <Badge
                                        :variant="
                                            option.is_active
                                                ? 'secondary'
                                                : 'outline'
                                        "
                                    >
                                        {{
                                            option.is_active
                                                ? 'Offered'
                                                : 'Retired'
                                        }}
                                    </Badge>
                                </div>
                            </TableCell>
                            <TableCell class="text-right">
                                <div class="flex justify-end gap-2">
                                    <Button
                                        v-if="editingId !== option.id"
                                        size="icon"
                                        variant="ghost"
                                        :data-test="`edit-specification-${option.id}-button`"
                                        @click="startEditing(option)"
                                    >
                                        <Pencil class="size-4" />
                                        <span class="sr-only">
                                            Rename {{ option.label }}
                                        </span>
                                    </Button>
                                    <AlertDialog>
                                        <AlertDialogTrigger as-child>
                                            <Button
                                                size="icon"
                                                variant="ghost"
                                                :data-test="`delete-specification-${option.id}-button`"
                                            >
                                                <Trash2
                                                    class="text-destructive size-4"
                                                />
                                                <span class="sr-only">
                                                    Delete {{ option.label }}
                                                </span>
                                            </Button>
                                        </AlertDialogTrigger>
                                        <AlertDialogContent>
                                            <AlertDialogHeader>
                                                <AlertDialogTitle>
                                                    Delete
                                                    {{ option.label }}?
                                                </AlertDialogTitle>
                                                <AlertDialogDescription>
                                                    Job orders already created
                                                    with this
                                                    {{
                                                        category.label
                                                            .toLowerCase()
                                                            .replace(/s$/, '')
                                                    }}
                                                    keep it — they store the
                                                    name, not a link to this
                                                    row. Retire it instead if
                                                    you only want it gone from
                                                    new job orders.
                                                </AlertDialogDescription>
                                            </AlertDialogHeader>
                                            <AlertDialogFooter>
                                                <AlertDialogCancel>
                                                    Cancel
                                                </AlertDialogCancel>
                                                <AlertDialogAction
                                                    :data-test="`confirm-delete-specification-${option.id}-button`"
                                                    @click="
                                                        deleteOption(option)
                                                    "
                                                >
                                                    Delete
                                                </AlertDialogAction>
                                            </AlertDialogFooter>
                                        </AlertDialogContent>
                                    </AlertDialog>
                                </div>
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>

                <form
                    class="flex flex-col gap-4 px-6 pb-6 @lg:flex-row @lg:items-end"
                    @submit.prevent="addOption(category.value)"
                >
                    <div class="flex min-w-0 flex-1 flex-col gap-2">
                        <Label :for="`new-${category.value}`">
                            Add a new option
                        </Label>
                        <Input
                            :id="`new-${category.value}`"
                            v-model="createForms[category.value].label"
                            class="w-full @lg:max-w-sm"
                            :placeholder="`New ${category.label.toLowerCase().replace(/s$/, '')}`"
                        />
                        <InputError
                            :message="createForms[category.value].errors.label"
                        />
                    </div>
                    <template v-if="category.value === 'print_size'">
                        <div class="flex min-w-0 flex-col gap-2">
                            <Label :for="`new-${category.value}-width`">
                                Width (in)
                            </Label>
                            <Input
                                :id="`new-${category.value}-width`"
                                v-model="
                                    createForms[category.value].width_inches
                                "
                                type="number"
                                step="0.1"
                                min="0.1"
                                class="w-full @lg:w-28"
                                placeholder="36"
                            />
                        </div>
                        <div class="flex min-w-0 flex-col gap-2">
                            <Label :for="`new-${category.value}-height`">
                                Height (in)
                            </Label>
                            <Input
                                :id="`new-${category.value}-height`"
                                v-model="
                                    createForms[category.value].height_inches
                                "
                                type="number"
                                step="0.1"
                                min="0.1"
                                class="w-full @lg:w-28"
                                placeholder="72"
                            />
                        </div>
                    </template>
                    <Button
                        type="submit"
                        :disabled="createForms[category.value].processing"
                        :data-test="`add-${category.value}-button`"
                    >
                        <Plus class="size-4" />
                        Add
                    </Button>
                </form>
            </CardContent>
        </Card>
    </PageContainer>
</template>

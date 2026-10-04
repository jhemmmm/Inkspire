<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { Pencil, Plus, Tag } from '@lucide/vue';
import { computed, ref } from 'vue';
import PricingEntryController from '@/actions/App/Http/Controllers/Admin/PricingEntryController';
import DataTableCard from '@/components/DataTableCard.vue';
import EmptyState from '@/components/EmptyState.vue';
import InputError from '@/components/InputError.vue';
import PageContainer from '@/components/PageContainer.vue';
import PageHeader from '@/components/PageHeader.vue';
import TableFilterBar from '@/components/TableFilterBar.vue';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
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
import { isSqFtUnit, money } from '@/lib/jobOrders';
import { index as productsIndex } from '@/routes/admin/products';

interface PricingEntry {
    id: number;
    name: string;
    base_price: string;
    unit: string | null;
    is_active: boolean;
}

const props = defineProps<{
    pricingEntries: PricingEntry[];
}>();

defineOptions({
    layout: {
        navItems: adminNavItems,
        breadcrumbs: [
            {
                title: 'Products & Services',
                href: productsIndex(),
            },
        ],
    },
});

const ALL = 'all';

const statusFilter = ref(ALL);

const statusFilterOptions = [
    { value: ALL, label: 'All statuses' },
    { value: 'active', label: 'Offered' },
    { value: 'retired', label: 'Retired' },
];

const { searchTerm, filtered: filteredEntries } = useTableFilter(
    () => props.pricingEntries,
    (entry) => [entry.name, entry.unit],
    {
        filters: [
            (entry) =>
                statusFilter.value === ALL ||
                entry.is_active === (statusFilter.value === 'active'),
        ],
    },
);

const filtersActive = computed(
    () => searchTerm.value.trim() !== '' || statusFilter.value !== ALL,
);

function clearFilters(): void {
    searchTerm.value = '';
    statusFilter.value = ALL;
}

/**
 * One dialog serves both New and Edit. The fields are assigned on every
 * open rather than `reset()`: Inertia re-bases a form's defaults on each
 * successful save, so `reset()` would hand the next "New Product" the
 * values of the product saved before it.
 */
const dialogOpen = ref(false);
const editing = ref<PricingEntry | null>(null);
const form = useForm({ name: '', base_price: '', unit: '' });

function openDialog(entry: PricingEntry | null): void {
    editing.value = entry;
    form.name = entry?.name ?? '';
    form.base_price = entry?.base_price ?? '';
    form.unit = entry?.unit ?? '';
    form.clearErrors();
    dialogOpen.value = true;
}

function save(): void {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            dialogOpen.value = false;
        },
    };

    if (editing.value) {
        form.patch(
            PricingEntryController.update(editing.value.id).url,
            options,
        );
    } else {
        form.post(PricingEntryController.store().url, options);
    }
}

/**
 * Pricing reads one thing from the free-text unit: whether it starts with
 * "sq ft" (see `isSqFtUnit`). Say which way the typed unit falls, so
 * "sqft" quietly charging per item is caught before it is saved.
 */
const chargeHint = computed(() => {
    const price = money(Number(form.base_price) || 0);

    return isSqFtUnit(form.unit)
        ? `Charged by area: ${price} × width × height (ft) × quantity.`
        : `Charged per item: ${price} × quantity. Start the unit with “sq ft” to charge by area instead.`;
});

const unitSuggestions = computed(() =>
    [
        ...new Set([
            'sq ft',
            'piece',
            ...props.pricingEntries
                .map((entry) => entry.unit)
                .filter((unit): unit is string => !!unit),
        ]),
    ].sort(),
);

/**
 * Retiring drops an entry from every Product/Service select while leaving
 * the job orders already priced from it untouched. It is the only removal
 * this catalog has — see `PricingEntryController::update`.
 */
function toggleActive(entry: PricingEntry, isActive: boolean): void {
    router.patch(
        PricingEntryController.update(entry.id).url,
        {
            name: entry.name,
            base_price: entry.base_price,
            unit: entry.unit,
            is_active: isActive,
        },
        { preserveScroll: true },
    );
}
</script>

<template>
    <Head title="Products & Services" />

    <PageContainer>
        <PageHeader
            title="Products & Services"
            description="The price list staff pick from when creating a job order. A new price applies to job orders created from now on — ones already quoted keep theirs. Retire an entry to hide it from new job orders without touching past ones."
        >
            <template #actions>
                <Button
                    data-test="new-product-button"
                    @click="openDialog(null)"
                >
                    <Plus class="size-4" />
                    New Product
                </Button>
            </template>
        </PageHeader>

        <TableFilterBar
            v-if="pricingEntries.length > 0"
            v-model:search="searchTerm"
            search-label="Search products and services"
            search-placeholder="Name or unit"
            :shown="filteredEntries.length"
            :total="pricingEntries.length"
            :active="filtersActive"
            @clear="clearFilters"
        >
            <div class="flex min-w-0 flex-col gap-2 @lg:w-48">
                <Label for="product-status-filter">Status</Label>
                <Select v-model="statusFilter">
                    <SelectTrigger id="product-status-filter" class="w-full">
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

        <DataTableCard>
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Product / service</TableHead>
                        <TableHead class="text-right">Price</TableHead>
                        <TableHead>Unit</TableHead>
                        <TableHead>Offered at intake</TableHead>
                        <TableHead class="text-right">Actions</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableEmpty v-if="pricingEntries.length === 0" :colspan="5">
                        <EmptyState
                            :icon="Tag"
                            title="No products or services yet"
                            description="Staff cannot price a job order until there is something to pick. Add the first one."
                        >
                            <template #actions>
                                <Button
                                    type="button"
                                    data-test="empty-new-product-button"
                                    @click="openDialog(null)"
                                >
                                    <Plus class="size-4" />
                                    New Product
                                </Button>
                            </template>
                        </EmptyState>
                    </TableEmpty>
                    <TableEmpty
                        v-else-if="filteredEntries.length === 0"
                        :colspan="5"
                    >
                        <EmptyState
                            title="No matches"
                            description="No products or services match that search or status filter."
                        >
                            <template #actions>
                                <Button
                                    type="button"
                                    variant="secondary"
                                    data-test="clear-product-filters-button"
                                    @click="clearFilters"
                                >
                                    Clear filters
                                </Button>
                            </template>
                        </EmptyState>
                    </TableEmpty>
                    <TableRow
                        v-for="entry in filteredEntries"
                        v-else
                        :key="entry.id"
                        :data-test="`product-${entry.id}-row`"
                    >
                        <TableCell class="font-medium">
                            {{ entry.name }}
                        </TableCell>
                        <TableCell class="text-right tabular-nums">
                            {{ money(Number(entry.base_price)) }}
                        </TableCell>
                        <TableCell class="text-muted-foreground">
                            {{ entry.unit ?? '—' }}
                        </TableCell>
                        <TableCell>
                            <div class="flex items-center gap-3">
                                <Switch
                                    :model-value="entry.is_active"
                                    :aria-label="`Offer ${entry.name} at intake`"
                                    :data-test="`toggle-product-${entry.id}`"
                                    @update:model-value="
                                        (value: boolean) =>
                                            toggleActive(entry, value)
                                    "
                                />
                                <Badge
                                    :variant="
                                        entry.is_active
                                            ? 'secondary'
                                            : 'outline'
                                    "
                                >
                                    {{
                                        entry.is_active ? 'Offered' : 'Retired'
                                    }}
                                </Badge>
                            </div>
                        </TableCell>
                        <TableCell class="text-right">
                            <Button
                                type="button"
                                variant="outline"
                                :data-test="`edit-product-${entry.id}-button`"
                                @click="openDialog(entry)"
                            >
                                <Pencil class="size-4" />
                                Edit
                                <span class="sr-only">{{ entry.name }}</span>
                            </Button>
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </DataTableCard>

        <Dialog v-model:open="dialogOpen">
            <DialogContent>
                <form class="space-y-4" @submit.prevent="save">
                    <DialogHeader>
                        <DialogTitle>
                            {{
                                editing
                                    ? `Edit ${editing.name}`
                                    : 'Add a product or service'
                            }}
                        </DialogTitle>
                        <DialogDescription>
                            {{
                                editing
                                    ? 'Job orders already quoted keep the price they were given. The change is recorded in the audit trail.'
                                    : 'Staff can pick it on a job order as soon as it is saved.'
                            }}
                        </DialogDescription>
                    </DialogHeader>

                    <div class="grid gap-2">
                        <Label for="product-name">Name</Label>
                        <Input
                            id="product-name"
                            v-model="form.name"
                            autocomplete="off"
                            placeholder="Tarpaulin"
                            required
                        />
                        <InputError :message="form.errors.name" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="product-price">Price (₱)</Label>
                        <Input
                            id="product-price"
                            v-model="form.base_price"
                            type="number"
                            step="0.01"
                            min="0"
                            inputmode="decimal"
                            class="tabular-nums"
                            placeholder="12.00"
                            required
                        />
                        <InputError :message="form.errors.base_price" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="product-unit">Unit</Label>
                        <Input
                            id="product-unit"
                            v-model="form.unit"
                            list="product-unit-suggestions"
                            autocomplete="off"
                            placeholder="sq ft, piece, roll…"
                            aria-describedby="product-charge-hint"
                        />
                        <datalist id="product-unit-suggestions">
                            <option
                                v-for="unit in unitSuggestions"
                                :key="unit"
                                :value="unit"
                            />
                        </datalist>
                        <p
                            id="product-charge-hint"
                            class="text-muted-foreground text-sm"
                            data-test="product-charge-hint"
                        >
                            {{ chargeHint }}
                        </p>
                        <InputError :message="form.errors.unit" />
                    </div>

                    <DialogFooter class="gap-2">
                        <DialogClose as-child>
                            <Button type="button" variant="secondary">
                                Cancel
                            </Button>
                        </DialogClose>
                        <Button
                            type="submit"
                            :disabled="form.processing"
                            data-test="save-product-button"
                        >
                            {{ editing ? 'Save Changes' : 'Add Product' }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </PageContainer>
</template>

<script setup lang="ts">
import { Form, Head, router } from '@inertiajs/vue3';
import { Ban, Plus } from '@lucide/vue';
import { computed, ref } from 'vue';
import ExpenseController from '@/actions/App/Http/Controllers/AccountingStaff/ExpenseController';
import DataTableCard from '@/components/DataTableCard.vue';
import EmptyState from '@/components/EmptyState.vue';
import InputError from '@/components/InputError.vue';
import PageContainer from '@/components/PageContainer.vue';
import StatCard from '@/components/StatCard.vue';
import PageHeader from '@/components/PageHeader.vue';
import DateRangeControl from '@/components/reports/DateRangeControl.vue';
import SearchableSelect, {
    type SearchableOption,
} from '@/components/SearchableSelect.vue';
import TableFilterBar from '@/components/TableFilterBar.vue';
import { Alert, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableEmpty,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import { useTableFilter } from '@/composables/useTableFilter';
import { accountingStaffNavItems } from '@/config/nav/accounting-staff';
import { index as expensesIndex } from '@/routes/accounting-staff/expenses';

interface ExpenseRow {
    id: number;
    expense_date: string;
    category: string;
    description: string | null;
    amount: number;
    recorded_by: string;
    voided_at: string | null;
    void_reason: string | null;
}

interface Filters {
    from: string;
    to: string;
}

const props = defineProps<{
    rows: ExpenseRow[];
    total: number;
    activeCount: number;
    voidedCount: number;
    categories: string[];
    filters: Filters;
}>();

defineOptions({
    layout: {
        navItems: accountingStaffNavItems,
        breadcrumbs: [{ title: 'Expenses', href: expensesIndex() }],
    },
});

function money(value: number): string {
    return `₱${Number(value).toLocaleString('en-PH', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })}`;
}

function dateLabel(value: string): string {
    const [year, month, day] = value.slice(0, 10).split('-').map(Number);
    return new Date(year, month - 1, day).toLocaleDateString('en-PH', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });
}

const rangeLabel = computed(() => {
    if (props.filters.from === props.filters.to) {
        return dateLabel(props.filters.from);
    }

    return `${dateLabel(props.filters.from)} – ${dateLabel(props.filters.to)}`;
});

const expenseCountLabel = computed(() => {
    if (props.activeCount === 0) {
        return 'No expenses';
    }

    return props.activeCount === 1
        ? '1 expense'
        : `${props.activeCount} expenses`;
});

/**
 * The expense count, with the voided tally appended when there is one --
 * flattened into a single string so the total tile can take it as a prop.
 */
const totalHint = computed(() => {
    if (props.voidedCount === 0) {
        return expenseCountLabel.value;
    }

    const entries = props.voidedCount === 1 ? 'entry' : 'entries';

    return `${expenseCountLabel.value} · ${props.voidedCount} voided ${entries} excluded`;
});

const ALL = 'all';

/**
 * Starts from the Admin-managed category list (System Configuration) and
 * appends any category still present on a row but no longer configured —
 * so a renamed/removed category doesn't silently vanish from the filter
 * for rows still carrying the old value (D3).
 */
const categoryOptions = computed<SearchableOption[]>(() => {
    const configured = new Set(props.categories);
    const extra = Array.from(
        new Set(props.rows.map((row) => row.category)),
    ).filter((category) => !configured.has(category));

    const all = [...props.categories, ...extra].sort((a, b) =>
        a.localeCompare(b),
    );

    return [
        { value: ALL, label: 'All categories' },
        ...all.map((category) => ({ value: category, label: category })),
    ];
});

const categoryFilter = ref(ALL);

const statusFilterOptions = [
    { value: ALL, label: 'All statuses' },
    { value: 'active', label: 'Active' },
    { value: 'voided', label: 'Voided' },
];

const statusFilter = ref(ALL);

const { searchTerm, filtered: filteredRows } = useTableFilter(
    () => props.rows,
    (row) => [row.description, row.category, row.recorded_by],
    {
        filters: [
            (row) =>
                categoryFilter.value === ALL ||
                row.category === categoryFilter.value,
            (row) => {
                if (statusFilter.value === ALL) {
                    return true;
                }
                return statusFilter.value === 'voided'
                    ? row.voided_at !== null
                    : row.voided_at === null;
            },
        ],
    },
);

const filtersActive = computed(
    () =>
        searchTerm.value.trim() !== '' ||
        categoryFilter.value !== ALL ||
        statusFilter.value !== ALL,
);

function clearFilters(): void {
    searchTerm.value = '';
    categoryFilter.value = ALL;
    statusFilter.value = ALL;
}

const loadingRange = ref(false);

function onApplyRange(range: { from: string; to: string }): void {
    router.get(
        expensesIndex.url(),
        { from: range.from, to: range.to },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            onStart: () => (loadingRange.value = true),
            onFinish: () => (loadingRange.value = false),
        },
    );
}

// Record Expense dialog
const recordCategory = ref('');

// Edit Expense dialog (single shared instance, controlled — Actions column
// only ever exposes the trigger for the currently non-voided row it opened
// for; a stale-voided reopen still renders the read-only Alert below).
const editDialogOpen = ref(false);
const editingExpense = ref<ExpenseRow | null>(null);
const editCategory = ref('');

function openEditDialog(row: ExpenseRow): void {
    editingExpense.value = row;
    editCategory.value = row.category;
    editDialogOpen.value = true;
}

// Void Expense dialog (single shared instance, controlled)
const voidDialogOpen = ref(false);
const voidingExpense = ref<ExpenseRow | null>(null);

function openVoidDialog(row: ExpenseRow): void {
    voidingExpense.value = row;
    voidDialogOpen.value = true;
}
</script>

<template>
    <Head title="Expenses" />

    <PageContainer>
        <PageHeader
            title="Expenses"
            description="Shop costs recorded against the business, offset against sales in the reports. Voided entries stay on the list but count toward nothing."
        >
            <template #actions>
                <Dialog>
                    <DialogTrigger as-child>
                        <Button
                            data-test="record-expense-button"
                            @click="recordCategory = ''"
                        >
                            <Plus class="size-4" />
                            Record Expense
                        </Button>
                    </DialogTrigger>
                    <DialogContent>
                        <Form
                            v-bind="ExpenseController.store.form()"
                            :options="{ preserveScroll: true }"
                            class="space-y-4"
                            v-slot="{ errors, processing }"
                        >
                            <DialogHeader>
                                <DialogTitle>Record an expense</DialogTitle>
                                <DialogDescription>
                                    This counts toward the Expenses and Summary
                                    reports for the date you set.
                                </DialogDescription>
                            </DialogHeader>

                            <input
                                type="hidden"
                                name="category"
                                :value="recordCategory"
                            />
                            <div class="grid gap-2">
                                <Label for="record-category">Category</Label>
                                <Select v-model="recordCategory">
                                    <SelectTrigger
                                        id="record-category"
                                        class="w-full"
                                    >
                                        <SelectValue
                                            placeholder="Choose a category"
                                        />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem
                                            v-for="category in categories"
                                            :key="category"
                                            :value="category"
                                        >
                                            {{ category }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <p class="text-muted-foreground text-sm">
                                    Categories are managed by the Admin in
                                    System Configuration.
                                </p>
                                <InputError :message="errors.category" />
                            </div>

                            <div class="grid gap-2">
                                <Label for="record-amount">Amount</Label>
                                <div class="flex items-center gap-2">
                                    <span class="text-muted-foreground text-sm"
                                        >₱</span
                                    >
                                    <Input
                                        id="record-amount"
                                        name="amount"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        class="w-full"
                                        placeholder="0.00"
                                    />
                                </div>
                                <InputError :message="errors.amount" />
                            </div>

                            <div class="grid gap-2">
                                <Label for="record-expense-date"
                                    >Expense Date</Label
                                >
                                <Input
                                    id="record-expense-date"
                                    name="expense_date"
                                    type="date"
                                    class="w-full"
                                />
                                <p class="text-muted-foreground text-sm">
                                    The day the cost was incurred, not the day
                                    you're entering it.
                                </p>
                                <InputError :message="errors.expense_date" />
                            </div>

                            <div class="grid gap-2">
                                <Label for="record-description"
                                    >Description (optional)</Label
                                >
                                <Textarea
                                    id="record-description"
                                    name="description"
                                    rows="3"
                                    placeholder="e.g. Meralco — August billing"
                                />
                                <p class="text-muted-foreground text-sm">
                                    A short note makes this row readable a month
                                    from now.
                                </p>
                                <InputError :message="errors.description" />
                            </div>

                            <DialogFooter class="gap-2">
                                <DialogClose as-child>
                                    <Button type="button" variant="secondary"
                                        >Cancel</Button
                                    >
                                </DialogClose>
                                <Button type="submit" :disabled="processing"
                                    >Record Expense</Button
                                >
                            </DialogFooter>
                        </Form>
                    </DialogContent>
                </Dialog>
            </template>
        </PageHeader>

        <DateRangeControl
            :from="filters.from"
            :to="filters.to"
            :loading="loadingRange"
            @apply="onApplyRange"
        />

        <!--
            StatCard is `h-full` so tiles in a grid row match heights. Bare in
            this flex column it stretched to the page's height and squeezed
            the page header shut, hiding Record Expense.
        -->
        <!--
            total/activeCount/voidedCount are server-computed over the full date
            range (ExpenseController::index) and must stay that way — they
            describe the range, not the filtered table below.
        -->
        <div class="grid grid-cols-1 gap-4 @lg:grid-cols-2 @3xl:grid-cols-3">
            <StatCard
                :label="`Total for ${rangeLabel}`"
                :value="money(total)"
                :hint="totalHint"
                ink="yellow"
            />
        </div>

        <TableFilterBar
            v-if="rows.length > 0"
            v-model:search="searchTerm"
            search-label="Search expenses"
            search-placeholder="Description, category or recorded by"
            :shown="filteredRows.length"
            :total="rows.length"
            :active="filtersActive"
            @clear="clearFilters"
        >
            <div class="flex min-w-0 flex-col gap-2 @lg:w-56">
                <Label for="expense-category-filter">Category</Label>
                <SearchableSelect
                    id="expense-category-filter"
                    v-model="categoryFilter"
                    :options="categoryOptions"
                    placeholder="All categories"
                />
            </div>
            <div class="flex min-w-0 flex-col gap-2 @lg:w-40">
                <Label for="expense-status-filter">Status</Label>
                <Select v-model="statusFilter">
                    <SelectTrigger id="expense-status-filter" class="w-full">
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

        <DataTableCard
            :aria-busy="loadingRange"
            class="transition-opacity"
            :class="loadingRange ? 'opacity-60' : ''"
        >
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Date</TableHead>
                        <TableHead>Category</TableHead>
                        <TableHead>Description</TableHead>
                        <TableHead class="text-right">Amount</TableHead>
                        <TableHead>Recorded By</TableHead>
                        <TableHead>Status</TableHead>
                        <TableHead class="text-right">Actions</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableEmpty v-if="rows.length === 0" :colspan="7">
                        <div
                            v-if="activeCount === 0 && voidedCount === 0"
                            class="flex flex-col items-center gap-1 text-center"
                        >
                            <p class="font-semibold">No expenses recorded</p>
                            <p class="text-muted-foreground">
                                Record the shop's costs here — utilities,
                                supplies, rent — so they show up in the Summary
                                of Sales &amp; Expenses report.
                            </p>
                        </div>
                        <div
                            v-else
                            class="flex flex-col items-center gap-1 text-center"
                        >
                            <p class="font-semibold">Nothing in this range</p>
                            <p class="text-muted-foreground">
                                No expenses dated between
                                {{ dateLabel(filters.from) }} and
                                {{ dateLabel(filters.to) }}. Try a wider date
                                range, or record one.
                            </p>
                        </div>
                    </TableEmpty>
                    <TableEmpty
                        v-else-if="filteredRows.length === 0"
                        :colspan="7"
                    >
                        <EmptyState
                            title="No matches"
                            description="No expenses in this range match that search, category or status filter."
                        >
                            <template #actions>
                                <Button
                                    type="button"
                                    variant="secondary"
                                    data-test="clear-expense-filters-button"
                                    @click="clearFilters"
                                >
                                    Clear filters
                                </Button>
                            </template>
                        </EmptyState>
                    </TableEmpty>
                    <TableRow v-for="row in filteredRows" v-else :key="row.id">
                        <TableCell>{{ dateLabel(row.expense_date) }}</TableCell>
                        <TableCell>{{ row.category }}</TableCell>
                        <TableCell>
                            <div class="flex flex-col">
                                <span>{{ row.description ?? '—' }}</span>
                                <span
                                    v-if="row.voided_at"
                                    class="text-muted-foreground line-clamp-2 text-sm"
                                >
                                    Voided: {{ row.void_reason }}
                                </span>
                            </div>
                        </TableCell>
                        <TableCell class="text-right tabular-nums">{{
                            money(row.amount)
                        }}</TableCell>
                        <TableCell>{{ row.recorded_by }}</TableCell>
                        <TableCell>
                            <Badge
                                v-if="row.voided_at"
                                variant="outline"
                                class="text-muted-foreground"
                                >Voided</Badge
                            >
                        </TableCell>
                        <TableCell class="text-right">
                            <span
                                v-if="row.voided_at"
                                class="text-muted-foreground text-sm"
                            >
                                Voided {{ dateLabel(row.voided_at) }}
                            </span>
                            <div v-else class="flex justify-end gap-2">
                                <Button
                                    variant="outline"
                                    size="sm"
                                    :data-test="`edit-expense-${row.id}-button`"
                                    @click="openEditDialog(row)"
                                >
                                    Edit
                                </Button>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    class="text-destructive"
                                    :data-test="`void-expense-${row.id}-button`"
                                    @click="openVoidDialog(row)"
                                >
                                    <Ban class="size-4" />
                                    Void
                                </Button>
                            </div>
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </DataTableCard>

        <Dialog v-model:open="editDialogOpen">
            <DialogContent v-if="editingExpense">
                <template v-if="editingExpense.voided_at">
                    <DialogHeader>
                        <DialogTitle>Edit this expense</DialogTitle>
                    </DialogHeader>
                    <Alert>
                        <AlertTitle>
                            This expense was voided on
                            {{ dateLabel(editingExpense.voided_at) }} and can't
                            be edited.
                        </AlertTitle>
                    </Alert>
                    <DialogFooter>
                        <DialogClose as-child>
                            <Button type="button">Close</Button>
                        </DialogClose>
                    </DialogFooter>
                </template>
                <Form
                    v-else
                    v-bind="ExpenseController.update.form(editingExpense.id)"
                    :options="{ preserveScroll: true }"
                    class="space-y-4"
                    v-slot="{ errors, processing }"
                >
                    <DialogHeader>
                        <DialogTitle>Edit this expense</DialogTitle>
                        <DialogDescription>
                            Corrections are recorded in the audit trail,
                            including what changed. Reports already printed from
                            an earlier version will differ.
                        </DialogDescription>
                    </DialogHeader>

                    <input
                        type="hidden"
                        name="category"
                        :value="editCategory"
                    />
                    <div class="grid gap-2">
                        <Label for="edit-category">Category</Label>
                        <Select v-model="editCategory">
                            <SelectTrigger id="edit-category" class="w-full">
                                <SelectValue placeholder="Choose a category" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="category in categories"
                                    :key="category"
                                    :value="category"
                                >
                                    {{ category }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <p class="text-muted-foreground text-sm">
                            Categories are managed by the Admin in System
                            Configuration.
                        </p>
                        <InputError :message="errors.category" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="edit-amount">Amount</Label>
                        <div class="flex items-center gap-2">
                            <span class="text-muted-foreground text-sm">₱</span>
                            <Input
                                id="edit-amount"
                                name="amount"
                                type="number"
                                step="0.01"
                                min="0"
                                class="w-full"
                                placeholder="0.00"
                                :default-value="editingExpense.amount"
                            />
                        </div>
                        <InputError :message="errors.amount" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="edit-expense-date">Expense Date</Label>
                        <Input
                            id="edit-expense-date"
                            name="expense_date"
                            type="date"
                            class="w-full"
                            :default-value="
                                editingExpense.expense_date.slice(0, 10)
                            "
                        />
                        <p class="text-muted-foreground text-sm">
                            The day the cost was incurred, not the day you're
                            entering it.
                        </p>
                        <InputError :message="errors.expense_date" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="edit-description"
                            >Description (optional)</Label
                        >
                        <Textarea
                            id="edit-description"
                            name="description"
                            rows="3"
                            placeholder="e.g. Meralco — August billing"
                            :default-value="editingExpense.description ?? ''"
                        />
                        <p class="text-muted-foreground text-sm">
                            A short note makes this row readable a month from
                            now.
                        </p>
                        <InputError :message="errors.description" />
                    </div>

                    <DialogFooter class="gap-2">
                        <DialogClose as-child>
                            <Button type="button" variant="secondary"
                                >Cancel</Button
                            >
                        </DialogClose>
                        <Button type="submit" :disabled="processing"
                            >Save Changes</Button
                        >
                    </DialogFooter>
                </Form>
            </DialogContent>
        </Dialog>

        <Dialog v-model:open="voidDialogOpen">
            <DialogContent v-if="voidingExpense">
                <Form
                    v-bind="ExpenseController.void.form(voidingExpense.id)"
                    :options="{ preserveScroll: true }"
                    class="space-y-4"
                    v-slot="{ errors, processing }"
                >
                    <DialogHeader>
                        <DialogTitle
                            >Void this
                            {{ money(voidingExpense.amount) }}
                            expense?</DialogTitle
                        >
                        <DialogDescription>
                            It stays on the list for the record but stops
                            counting toward every report. This can't be undone —
                            if the entry just needs a correction, edit it
                            instead.
                        </DialogDescription>
                    </DialogHeader>

                    <div class="grid gap-2">
                        <Label for="void-reason">Reason</Label>
                        <Textarea
                            id="void-reason"
                            name="reason"
                            rows="3"
                            placeholder="e.g. Duplicate of the August Meralco entry"
                        />
                        <p class="text-muted-foreground text-sm">
                            This is recorded in the audit trail beside your
                            name.
                        </p>
                        <InputError :message="errors.reason" />
                    </div>

                    <DialogFooter class="gap-2">
                        <DialogClose as-child>
                            <Button type="button" variant="secondary"
                                >Cancel</Button
                            >
                        </DialogClose>
                        <Button
                            type="submit"
                            variant="destructive"
                            :disabled="processing"
                            >Void Expense</Button
                        >
                    </DialogFooter>
                </Form>
            </DialogContent>
        </Dialog>
    </PageContainer>
</template>

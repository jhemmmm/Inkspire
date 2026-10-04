<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ChevronRight, Pencil, Users } from '@lucide/vue';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import CustomerController from '@/actions/App/Http/Controllers/Admin/CustomerController';
import DataTableCard from '@/components/DataTableCard.vue';
import EmptyState from '@/components/EmptyState.vue';
import InputError from '@/components/InputError.vue';
import JobOrderTotal from '@/components/JobOrderTotal.vue';
import PageContainer from '@/components/PageContainer.vue';
import PageHeader from '@/components/PageHeader.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import TableFilterBar from '@/components/TableFilterBar.vue';
import TablePagination from '@/components/TablePagination.vue';
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
    Table,
    TableBody,
    TableCell,
    TableEmpty,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import { useLivePoll } from '@/composables/useLivePoll';
import { adminNavItems } from '@/config/nav/admin';
import { useBusinessTime } from '@/composables/useBusinessTime';
import {
    jobOrderStatusBadge,
    jobOrderStatusLabel,
    paymentStatusBadge,
    paymentStatusLabel,
} from '@/lib/jobOrders';
import { index as customersIndex } from '@/routes/admin/customers';
import { index as jobOrdersIndex } from '@/routes/admin/job-orders';

interface CustomerJobOrder {
    id: number;
    number: string | null;
    description: string;
    display_status: string;
    payment_status: string;
    total_amount: number | null;
    display_total: number | null;
    is_rush: boolean;
    created_at: string;
}

interface Customer {
    id: number;
    name: string;
    organization: string | null;
    contact_number: string;
    email: string;
    address: string;
    job_orders_count: number;
    /** The newest few only; `job_orders_count` is the real total. */
    job_orders: CustomerJobOrder[];
}

/** Shape of Laravel's LengthAwarePaginator::toArray(), passed through as-is. */
interface PaginatedCustomers {
    data: Customer[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}

const props = defineProps<{
    customers: PaginatedCustomers;
    filters: { q?: string };
}>();

defineOptions({
    layout: {
        navItems: adminNavItems,
        breadcrumbs: [
            {
                title: 'Customers',
                href: customersIndex(),
            },
        ],
    },
});

// A customer Frontline just registered, or a job order that just moved,
// shows up without a reload.
useLivePoll(['customers']);

const searchTerm = ref(props.filters.q ?? '');

function visit(page?: number): void {
    router.get(
        customersIndex.url(),
        {
            ...(searchTerm.value.trim() !== ''
                ? { q: searchTerm.value.trim() }
                : {}),
            ...(page ? { page } : {}),
        },
        {
            preserveState: true,
            // A new page starts at the top; a search keeps its place.
            preserveScroll: !page,
            replace: true,
        },
    );
}

let searchTimer: ReturnType<typeof setTimeout> | undefined;

// Debounced so typing a name fires one request, not one per keystroke.
watch(searchTerm, () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => visit(), 300);
});

// A search still waiting to fire would pull the Admin back to this page
// after they left it.
onBeforeUnmount(() => clearTimeout(searchTimer));

const searchActive = computed(() => searchTerm.value.trim() !== '');

/** One customer open at a time, so the list stays a list. */
const expandedId = ref<number | null>(null);

function toggle(customer: Customer): void {
    expandedId.value = expandedId.value === customer.id ? null : customer.id;
}

const editDialogOpen = ref(false);
const editing = ref<Customer | null>(null);
const form = useForm({
    name: '',
    organization: '',
    contact_number: '',
    email: '',
    address: '',
});

/**
 * The fields are assigned on every open rather than `reset()`: Inertia
 * re-bases a form's defaults on each successful save, so `reset()` would
 * hand the next customer the details of the one saved before them.
 */
function openEditDialog(customer: Customer): void {
    editing.value = customer;
    form.name = customer.name;
    form.organization = customer.organization ?? '';
    form.contact_number = customer.contact_number;
    form.email = customer.email;
    form.address = customer.address;
    form.clearErrors();
    editDialogOpen.value = true;
}

function save(): void {
    if (!editing.value) {
        return;
    }

    form.patch(CustomerController.update(editing.value.id).url, {
        preserveScroll: true,
        onSuccess: () => {
            editDialogOpen.value = false;
        },
    });
}

/** The shop's own day: an order taken at 7 AM Manila is not yesterday's. */
function orderedOn(createdAt: string): string {
    return formatInstant(createdAt, {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });
}
const { formatInstant } = useBusinessTime();
</script>

<template>
    <Head title="Customers" />

    <PageContainer>
        <PageHeader
            title="Customers"
            description="Everyone the shop has served, walk-in or online. Open a customer to see their latest job orders, or correct their contact details. Customers are registered by Frontline Staff or when they order online."
        />

        <TableFilterBar
            v-if="customers.total > 0 || searchActive"
            v-model:search="searchTerm"
            search-label="Search customers"
            search-placeholder="Name, organization, mobile number or email"
            :shown="customers.data.length"
            :total="customers.total"
            :active="searchActive"
            @clear="searchTerm = ''"
        />

        <DataTableCard>
            <Table>
                <TableHeader>
                    <!-- Four columns, not six: with organization and email
                         in columns of their own, Edit sat beyond the card's
                         edge on a laptop with the sidebar open. -->
                    <TableRow>
                        <TableHead>Customer</TableHead>
                        <TableHead>Contact</TableHead>
                        <TableHead class="text-right">Job orders</TableHead>
                        <TableHead class="text-right">Actions</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableEmpty
                        v-if="customers.data.length === 0 && !searchActive"
                        :colspan="4"
                    >
                        <EmptyState
                            :icon="Users"
                            title="No customers yet"
                            description="A customer appears here once Frontline Staff register them at New Visit, or once they confirm an order placed online."
                        />
                    </TableEmpty>
                    <TableEmpty
                        v-else-if="customers.data.length === 0"
                        :colspan="4"
                    >
                        <EmptyState
                            title="No customer matches that"
                            description="Check the spelling, or search by mobile number or email instead."
                        >
                            <template #actions>
                                <Button
                                    type="button"
                                    variant="secondary"
                                    data-test="clear-customer-search-button"
                                    @click="searchTerm = ''"
                                >
                                    Clear search
                                </Button>
                            </template>
                        </EmptyState>
                    </TableEmpty>
                    <template
                        v-for="customer in customers.data"
                        v-else
                        :key="customer.id"
                    >
                        <TableRow
                            tabindex="0"
                            class="focus-visible:outline-ring cursor-pointer focus-visible:outline-2 focus-visible:-outline-offset-2"
                            :aria-expanded="expandedId === customer.id"
                            :data-test="`customer-${customer.id}-row`"
                            @click="toggle(customer)"
                            @keyup.enter.self="toggle(customer)"
                        >
                            <TableCell>
                                <span class="flex items-center gap-2">
                                    <ChevronRight
                                        aria-hidden="true"
                                        :class="[
                                            'text-muted-foreground size-4 shrink-0 transition-transform motion-reduce:transition-none',
                                            expandedId === customer.id &&
                                                'rotate-90',
                                        ]"
                                    />
                                    <span class="flex flex-col">
                                        <span class="font-medium">
                                            {{ customer.name }}
                                        </span>
                                        <span
                                            v-if="customer.organization"
                                            class="text-muted-foreground text-sm"
                                        >
                                            {{ customer.organization }}
                                        </span>
                                    </span>
                                </span>
                            </TableCell>
                            <TableCell>
                                <span class="flex flex-col">
                                    <span class="tabular-nums">
                                        {{ customer.contact_number }}
                                    </span>
                                    <span class="text-muted-foreground text-sm">
                                        {{ customer.email }}
                                    </span>
                                </span>
                            </TableCell>
                            <TableCell class="text-right tabular-nums">
                                {{ customer.job_orders_count }}
                            </TableCell>
                            <TableCell class="text-right">
                                <Button
                                    type="button"
                                    variant="outline"
                                    :data-test="`edit-customer-${customer.id}-button`"
                                    @click.stop="openEditDialog(customer)"
                                >
                                    <Pencil class="size-4" />
                                    Edit
                                    <span class="sr-only">
                                        {{ customer.name }}
                                    </span>
                                </Button>
                            </TableCell>
                        </TableRow>

                        <TableRow
                            v-if="expandedId === customer.id"
                            class="bg-muted/40 hover:bg-muted/40"
                            :data-test="`customer-${customer.id}-job-orders`"
                        >
                            <TableCell :colspan="4">
                                <!--
                                    As wide as the visible card, not the
                                    table's scroll width, and held in view on
                                    both edges. See EmptyState.vue, which
                                    solves the same problem the same way.
                                -->
                                <div
                                    class="sticky right-4 left-4 flex w-[calc(100cqw-2rem)] max-w-full flex-col gap-3 py-2 whitespace-normal"
                                >
                                    <!-- On a narrow card the Contact and
                                         Actions columns are a sideways scroll
                                         away, so they are repeated here. -->
                                    <div
                                        class="flex flex-col gap-2 text-sm @2xl:hidden"
                                    >
                                        <span class="tabular-nums">
                                            {{ customer.contact_number }}
                                        </span>
                                        <span class="break-all">
                                            {{ customer.email }}
                                        </span>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            class="w-fit"
                                            :data-test="`edit-customer-${customer.id}-panel-button`"
                                            @click="openEditDialog(customer)"
                                        >
                                            <Pencil class="size-4" />
                                            Edit details
                                        </Button>
                                    </div>

                                    <p class="text-muted-foreground text-sm">
                                        {{ customer.address }}
                                    </p>

                                    <p
                                        v-if="customer.job_orders.length === 0"
                                        class="text-muted-foreground text-sm"
                                    >
                                        No job orders yet.
                                    </p>

                                    <template v-else>
                                        <p class="text-sm font-semibold">
                                            {{
                                                customer.job_orders_count >
                                                customer.job_orders.length
                                                    ? `Latest ${customer.job_orders.length} of ${customer.job_orders_count} job orders`
                                                    : 'Job orders'
                                            }}
                                        </p>
                                        <ul
                                            class="divide-border bg-card border-border divide-y rounded-lg border"
                                        >
                                            <li
                                                v-for="jobOrder in customer.job_orders"
                                                :key="jobOrder.id"
                                                class="flex flex-wrap items-center gap-x-4 gap-y-2 px-4 py-3 text-sm"
                                            >
                                                <span
                                                    class="flex min-w-0 flex-1 basis-56 flex-col"
                                                >
                                                    <span
                                                        class="flex items-center gap-2 font-medium tabular-nums"
                                                    >
                                                        {{
                                                            jobOrder.number ??
                                                            '—'
                                                        }}
                                                        <Badge
                                                            v-if="
                                                                jobOrder.is_rush
                                                            "
                                                            variant="outline"
                                                        >
                                                            Rush
                                                        </Badge>
                                                    </span>
                                                    <span
                                                        class="text-muted-foreground"
                                                    >
                                                        {{
                                                            jobOrder.description
                                                        }}
                                                        ·
                                                        {{
                                                            orderedOn(
                                                                jobOrder.created_at,
                                                            )
                                                        }}
                                                    </span>
                                                </span>
                                                <span
                                                    class="flex flex-wrap items-center gap-2"
                                                >
                                                    <StatusBadge
                                                        :tone="
                                                            jobOrderStatusBadge(
                                                                jobOrder.display_status,
                                                            )
                                                        "
                                                    >
                                                        {{
                                                            jobOrderStatusLabel(
                                                                jobOrder.display_status,
                                                            )
                                                        }}
                                                    </StatusBadge>
                                                    <StatusBadge
                                                        :tone="
                                                            paymentStatusBadge(
                                                                jobOrder.payment_status,
                                                            )
                                                        "
                                                    >
                                                        {{
                                                            paymentStatusLabel(
                                                                jobOrder.payment_status,
                                                            )
                                                        }}
                                                    </StatusBadge>
                                                </span>
                                                <span
                                                    class="w-28 text-right font-medium"
                                                >
                                                    <JobOrderTotal
                                                        :job-order="jobOrder"
                                                    />
                                                </span>
                                            </li>
                                        </ul>
                                        <Button
                                            as-child
                                            variant="link"
                                            class="h-auto w-fit p-0"
                                        >
                                            <Link
                                                :href="
                                                    jobOrdersIndex({
                                                        query: {
                                                            q: customer.name,
                                                        },
                                                    })
                                                "
                                                :data-test="`customer-${customer.id}-all-job-orders-link`"
                                            >
                                                View all in Job Orders
                                            </Link>
                                        </Button>
                                    </template>
                                </div>
                            </TableCell>
                        </TableRow>
                    </template>
                </TableBody>
            </Table>
        </DataTableCard>

        <TablePagination :paginator="customers" @update:page="visit" />

        <Dialog v-model:open="editDialogOpen">
            <DialogContent>
                <form class="space-y-4" @submit.prevent="save">
                    <DialogHeader>
                        <DialogTitle>Edit {{ editing?.name }}</DialogTitle>
                        <DialogDescription>
                            Job orders already placed keep their history. The
                            change is recorded in the audit trail.
                        </DialogDescription>
                    </DialogHeader>

                    <div class="grid gap-2">
                        <Label for="customer-name">Name</Label>
                        <Input
                            id="customer-name"
                            v-model="form.name"
                            autocomplete="off"
                            required
                        />
                        <InputError :message="form.errors.name" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="customer-organization">
                            Organization (optional)
                        </Label>
                        <Input
                            id="customer-organization"
                            v-model="form.organization"
                            autocomplete="off"
                        />
                        <InputError :message="form.errors.organization" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="customer-contact-number">
                            Mobile number
                        </Label>
                        <Input
                            id="customer-contact-number"
                            v-model="form.contact_number"
                            type="tel"
                            inputmode="tel"
                            autocomplete="off"
                            class="tabular-nums"
                            required
                        />
                        <InputError :message="form.errors.contact_number" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="customer-email">Email</Label>
                        <Input
                            id="customer-email"
                            v-model="form.email"
                            type="email"
                            inputmode="email"
                            autocomplete="off"
                            aria-describedby="customer-email-hint"
                            required
                        />
                        <p
                            id="customer-email-hint"
                            class="text-muted-foreground text-sm"
                        >
                            Design review and payment links are sent here.
                        </p>
                        <InputError :message="form.errors.email" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="customer-address">Address</Label>
                        <Textarea
                            id="customer-address"
                            v-model="form.address"
                            rows="2"
                            autocomplete="off"
                            required
                        />
                        <InputError :message="form.errors.address" />
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
                            data-test="save-customer-button"
                        >
                            Save Changes
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </PageContainer>
</template>

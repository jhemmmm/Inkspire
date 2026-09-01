<script setup lang="ts">
import { Form, Head, router, useForm } from '@inertiajs/vue3';
import { X } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import CustomerController from '@/actions/App/Http/Controllers/FrontlineStaff/CustomerController';
import QueueEntryController from '@/actions/App/Http/Controllers/FrontlineStaff/QueueEntryController';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
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
import { frontlineStaffNavItems } from '@/config/nav/frontline-staff';
import { newVisit } from '@/routes/frontline-staff';

interface CustomerRecord {
    id: number;
    name: string;
    contact_number: string;
    email: string;
    address: string;
}

interface JobOrderRow {
    description: string;
    type: 'type_a' | 'type_b';
    file: File | null;
}

interface ConfirmedJobOrder {
    id: number;
    description: string;
    type: string;
}

interface ConfirmedQueueEntry {
    id: number;
    queue_number: number;
    status: string;
    job_orders: ConfirmedJobOrder[];
}

const props = defineProps<{
    customers: CustomerRecord[];
    filters: { q?: string };
    selectedCustomer: CustomerRecord | null;
    confirmedQueueEntry: ConfirmedQueueEntry | null;
}>();

defineOptions({
    layout: {
        navItems: frontlineStaffNavItems,
        breadcrumbs: [
            {
                title: 'New Visit',
                href: newVisit(),
            },
        ],
    },
});

const searchTerm = ref(props.filters.q ?? '');
// D-04's actual gate condition — a key being present (even set to '') means a
// search has run; distinct from "customers happens to be empty on first load".
const hasSearched = computed(() => 'q' in props.filters);
const selected = ref<CustomerRecord | null>(props.selectedCustomer);
const showEmptyQueryError = ref(false);

// Inertia can preserve this component instance across the post-registration
// redirect instead of remounting it — keep `selected` in sync when that happens.
watch(
    () => props.selectedCustomer,
    (value) => {
        if (value) {
            selected.value = value;
        }
    },
);

function search(): void {
    if (!searchTerm.value.trim()) {
        showEmptyQueryError.value = true;

        return;
    }

    showEmptyQueryError.value = false;

    router.get(
        newVisit.url(),
        { q: searchTerm.value },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

function selectCustomer(customer: CustomerRecord): void {
    selected.value = customer;
}

function emptyJobOrderRow(): JobOrderRow {
    return { description: '', type: 'type_a', file: null };
}

const intakeForm = useForm({
    customer_id: 0,
    job_orders: [emptyJobOrderRow()] as JobOrderRow[],
});

// The intake form always targets whichever customer is currently selected,
// including when `selected` is set client-side via `selectCustomer()`.
watch(
    selected,
    (customer: CustomerRecord | null) => {
        if (customer) {
            intakeForm.customer_id = customer.id;
        }
    },
    { immediate: true },
);

function addRow(): void {
    intakeForm.job_orders.push(emptyJobOrderRow());
}

function removeRow(index: number): void {
    intakeForm.job_orders.splice(index, 1);
}

function onFileChange(row: JobOrderRow, event: Event): void {
    row.file = (event.target as HTMLInputElement).files?.[0] ?? null;
}

function submitIntake(): void {
    intakeForm.post(QueueEntryController.store().url, {
        forceFormData: true,
        preserveScroll: true,
    });
}
</script>

<template>
    <Head title="New Visit" />

    <div
        class="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4"
    >
        <h1 class="text-[28px] leading-[1.2] font-semibold">New Visit</h1>

        <template v-if="!selected">
            <form
                class="flex flex-wrap items-end gap-4"
                @submit.prevent="search"
            >
                <div class="flex flex-col gap-1">
                    <Label for="customer-search">Search Customers</Label>
                    <Input
                        id="customer-search"
                        v-model="searchTerm"
                        class="w-80"
                        placeholder="Name or contact number"
                    />
                </div>
                <Button type="submit" data-test="search-customers-button">
                    Search Customers
                </Button>
            </form>

            <p v-if="showEmptyQueryError" class="text-muted-foreground text-sm">
                Enter a name or contact number to search.
            </p>

            <div
                v-if="hasSearched"
                class="border-sidebar-border/70 dark:border-sidebar-border overflow-hidden rounded-xl border"
            >
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Name</TableHead>
                            <TableHead>Contact Number</TableHead>
                            <TableHead>Email</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableEmpty v-if="customers.length === 0" :colspan="3">
                            <div
                                class="flex flex-col items-center gap-1 text-center"
                            >
                                <p class="font-semibold">
                                    No matching customers
                                </p>
                                <p class="text-muted-foreground">
                                    No customer matches this name or contact
                                    number. Register a new customer below to
                                    continue.
                                </p>
                            </div>
                        </TableEmpty>
                        <TableRow
                            v-for="customer in customers"
                            v-else
                            :key="customer.id"
                            class="cursor-pointer"
                            :data-test="`select-customer-${customer.id}-row`"
                            @click="selectCustomer(customer)"
                        >
                            <TableCell>{{ customer.name }}</TableCell>
                            <TableCell>{{ customer.contact_number }}</TableCell>
                            <TableCell>{{ customer.email }}</TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>

            <Card v-if="hasSearched && customers.length === 0">
                <CardHeader>
                    <CardTitle>Register New Customer</CardTitle>
                </CardHeader>
                <CardContent>
                    <Form
                        v-bind="CustomerController.store.form()"
                        class="space-y-6"
                        v-slot="{ errors, processing }"
                    >
                        <div class="grid gap-2">
                            <Label for="name">Name</Label>
                            <Input
                                id="name"
                                name="name"
                                required
                                placeholder="Full name"
                            />
                            <InputError :message="errors.name" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="contact_number">Contact Number</Label>
                            <Input
                                id="contact_number"
                                name="contact_number"
                                type="tel"
                                required
                                placeholder="09XXXXXXXXX"
                            />
                            <InputError :message="errors.contact_number" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="email">Email</Label>
                            <Input
                                id="email"
                                name="email"
                                type="email"
                                required
                                placeholder="Email address"
                            />
                            <InputError :message="errors.email" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="address">Address</Label>
                            <Textarea
                                id="address"
                                name="address"
                                rows="3"
                                required
                                placeholder="Complete address"
                            />
                            <InputError :message="errors.address" />
                        </div>

                        <Button
                            type="submit"
                            :disabled="processing"
                            data-test="register-customer-button"
                        >
                            Register New Customer
                        </Button>
                    </Form>
                </CardContent>
            </Card>
        </template>

        <Card v-else>
            <CardHeader>
                <CardTitle>Customer</CardTitle>
            </CardHeader>
            <CardContent class="flex flex-col gap-1">
                <p class="font-semibold">{{ selected.name }}</p>
                <p class="text-muted-foreground">
                    {{ selected.contact_number }}
                </p>
                <p class="text-muted-foreground">{{ selected.email }}</p>
            </CardContent>
        </Card>

        <template v-if="selected && !confirmedQueueEntry">
            <Card v-for="(row, index) in intakeForm.job_orders" :key="index">
                <CardHeader class="flex flex-row items-center justify-between">
                    <CardTitle>Job Order {{ index + 1 }}</CardTitle>
                    <Button
                        v-if="intakeForm.job_orders.length > 1"
                        type="button"
                        variant="ghost"
                        size="icon"
                        :data-test="`remove-job-order-${index}-button`"
                        @click="removeRow(index)"
                    >
                        <X class="size-4" />
                        <span class="sr-only">Remove job order</span>
                    </Button>
                </CardHeader>
                <CardContent class="grid gap-4">
                    <div class="grid gap-2">
                        <Label :for="`job-order-description-${index}`">
                            Product / Service
                        </Label>
                        <Input
                            :id="`job-order-description-${index}`"
                            v-model="row.description"
                            placeholder="Tarpaulin, 3x5ft"
                        />
                        <InputError
                            :message="
                                intakeForm.errors[
                                    `job_orders.${index}.description`
                                ]
                            "
                        />
                    </div>

                    <div class="grid gap-2">
                        <Label>Job Order Type</Label>
                        <RadioGroup v-model="row.type">
                            <div class="flex items-center gap-2">
                                <RadioGroupItem
                                    :id="`job-order-type-a-${index}`"
                                    value="type_a"
                                />
                                <Label :for="`job-order-type-a-${index}`">
                                    Type A — Print-ready file
                                </Label>
                            </div>
                            <div class="flex items-center gap-2">
                                <RadioGroupItem
                                    :id="`job-order-type-b-${index}`"
                                    value="type_b"
                                />
                                <Label :for="`job-order-type-b-${index}`">
                                    Type B — Needs consultation
                                </Label>
                            </div>
                        </RadioGroup>
                        <InputError
                            :message="
                                intakeForm.errors[`job_orders.${index}.type`]
                            "
                        />
                    </div>

                    <div v-if="row.type === 'type_a'" class="grid gap-2">
                        <Label :for="`job-order-file-${index}`">
                            Attach File
                        </Label>
                        <Input
                            :id="`job-order-file-${index}`"
                            type="file"
                            @change="onFileChange(row, $event)"
                        />
                        <InputError
                            :message="
                                intakeForm.errors[`job_orders.${index}.file`]
                            "
                        />
                    </div>
                </CardContent>
            </Card>

            <div class="flex flex-wrap gap-4">
                <Button
                    type="button"
                    variant="secondary"
                    data-test="add-job-order-row-button"
                    @click="addRow"
                >
                    + Add Another Job Order
                </Button>
                <Button
                    type="button"
                    :disabled="intakeForm.processing"
                    data-test="add-to-queue-button"
                    @click="submitIntake"
                >
                    Add to Queue
                </Button>
            </div>
        </template>
    </div>
</template>

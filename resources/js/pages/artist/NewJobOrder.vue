<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { Plus, Ticket, UserPlus, Users } from '@lucide/vue';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import JobOrderIntakeController from '@/actions/App/Http/Controllers/Artist/JobOrderIntakeController';
import InputError from '@/components/InputError.vue';
import JobOrderRowFields, {
    emptyJobOrderRow,
    jobOrderRowErrors,
    type JobOrderRow,
} from '@/components/JobOrderRowFields.vue';
import PageContainer from '@/components/PageContainer.vue';
import PageHeader from '@/components/PageHeader.vue';
import SearchableSelect from '@/components/SearchableSelect.vue';
import SectionHeading from '@/components/SectionHeading.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { artistNavItems } from '@/config/nav/artist';
import { money, rowLineAmount } from '@/lib/jobOrders';
import { dashboard } from '@/routes/artist';
import { create } from '@/routes/artist/job-orders';

interface CustomerOption {
    id: number;
    name: string;
    organization: string | null;
    contact_number: string;
}

interface PricingEntry {
    id: number;
    name: string;
    base_price: string;
    unit: string | null;
}

const props = defineProps<{
    /** A short list: the first few, or the matches for the current search. */
    customers: CustomerOption[];
    hasMoreCustomers: boolean;
    specificationOptions: Record<string, string[]>;
    pricingEntries: PricingEntry[];
    printSizeDimensions: Record<
        string,
        { width_inches: number | null; height_inches: number | null }
    >;
    rushFeePercentage: number;
    acceptedFileFormats: string[];
}>();

defineOptions({
    layout: {
        navItems: artistNavItems,
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: 'New Job Order', href: create() },
        ],
    },
});

const customerMode = ref<'existing' | 'new'>('existing');

/**
 * The customer picked from an earlier search. The list moves on with every
 * search, so the picked one is kept here or its name would vanish from the
 * field the moment the Artist typed something else.
 */
const pickedCustomer = ref<CustomerOption | null>(null);

const customerOptions = computed(() => {
    const picked = pickedCustomer.value;
    const listed =
        picked && !props.customers.some(({ id }) => id === picked.id)
            ? [picked, ...props.customers]
            : props.customers;

    return listed.map((customer) => ({
        value: String(customer.id),
        label: customer.name,
        hint: [customer.organization, customer.contact_number]
            .filter(Boolean)
            .join(' · '),
    }));
});

const emptyCustomer = () => ({
    name: '',
    organization: '',
    contact_number: '',
    email: '',
    address: '',
});

const form = useForm({
    customer_id: '',
    customer: emptyCustomer(),
    job_orders: [emptyJobOrderRow()] as JobOrderRow[],
});

watch(
    () => form.customer_id,
    (id) => {
        pickedCustomer.value =
            props.customers.find((customer) => String(customer.id) === id) ??
            null;
    },
);

const searchingCustomers = ref(false);

// The search the listed customers answer, and the newest one asked for. They
// start from the address: after a refresh the list is still the last
// search's, while the field itself is empty again.
let listedSearch =
    new URL(usePage().url, 'http://localhost').searchParams.get(
        'customer_search',
    ) ?? '';
let requestedSearch = listedSearch;
let customerSearchTimer: ReturnType<typeof setTimeout> | undefined;

/**
 * Ask the server for the customers matching what was typed. Debounced so a
 * name fires one request, not one per letter, and limited to the two
 * customer props so the form (and any file already attached) is untouched.
 */
function searchCustomers(term: string): void {
    if (term === listedSearch && term === requestedSearch) {
        return;
    }

    requestedSearch = term;
    searchingCustomers.value = true;
    clearTimeout(customerSearchTimer);
    customerSearchTimer = setTimeout(() => {
        router.get(create.url(), term === '' ? {} : { customer_search: term }, {
            only: ['customers', 'hasMoreCustomers'],
            preserveState: true,
            preserveScroll: true,
            replace: true,
            onSuccess: () => {
                listedSearch = term;
            },
            // A newer search cancels this one, which also ends here.
            onFinish: () => {
                if (term === requestedSearch) {
                    searchingCustomers.value = false;
                }
            },
        });
    }, 300);
}

// A search still waiting to fire would pull the Artist back to this page
// after they left it, or cancel the save they just started.
onBeforeUnmount(() => clearTimeout(customerSearchTimer));

// The unused mode's value is cleared on every switch, and the payload below
// carries only the active mode's key, so the server never sees both.
function useExistingCustomer(): void {
    customerMode.value = 'existing';
    form.customer = emptyCustomer();
    form.clearErrors();
}

function useNewCustomer(): void {
    customerMode.value = 'new';
    form.customer_id = '';
    form.clearErrors();
}

function addRow(): void {
    form.job_orders.push(emptyJobOrderRow());
}

function removeRow(index: number): void {
    form.job_orders.splice(index, 1);
}

const estimatedTotal = computed(() =>
    form.job_orders.reduce(
        (sum: number, row: JobOrderRow) =>
            sum + (rowLineAmount(row, props.pricingEntries) ?? 0),
        0,
    ),
);

function submit(): void {
    clearTimeout(customerSearchTimer);

    form.transform((data) =>
        customerMode.value === 'existing'
            ? { customer_id: data.customer_id, job_orders: data.job_orders }
            : { customer: data.customer, job_orders: data.job_orders },
    ).post(JobOrderIntakeController.store().url, {
        forceFormData: true,
        preserveScroll: true,
        onError: () =>
            toast.error(
                'Nothing was saved. Fix the highlighted fields and try again.',
            ),
    });
}
</script>

<template>
    <Head title="New Job Order" />

    <PageContainer class="group max-w-none gap-0 p-0">
        <div
            class="@container mx-auto flex w-full max-w-[100rem] flex-1 flex-col gap-6 px-4 pt-4 pb-12 sm:p-6 lg:p-8"
        >
            <PageHeader
                title="New Job Order"
                description="For a client who sent their request by email. The job order goes straight to your queue and the client is emailed a tracking link."
            />

            <Card>
                <CardContent class="flex flex-col gap-4">
                    <div class="flex flex-wrap items-end justify-between gap-3">
                        <SectionHeading
                            title="Customer"
                            :description="
                                customerMode === 'existing'
                                    ? 'Pick the client from the list, or register them if they are new.'
                                    : 'Register the client. They are saved together with this job order.'
                            "
                        />
                        <Button
                            v-if="customerMode === 'existing'"
                            type="button"
                            variant="secondary"
                            data-test="new-customer-toggle"
                            @click="useNewCustomer"
                        >
                            <UserPlus class="size-4" />
                            New customer
                        </Button>
                        <Button
                            v-else
                            type="button"
                            variant="secondary"
                            data-test="existing-customer-toggle"
                            @click="useExistingCustomer"
                        >
                            <Users class="size-4" />
                            Pick an existing customer
                        </Button>
                    </div>

                    <div v-if="customerMode === 'existing'" class="grid gap-2">
                        <Label for="customer-select">Customer</Label>
                        <SearchableSelect
                            id="customer-select"
                            v-model="form.customer_id"
                            data-test="customer-select"
                            :options="customerOptions"
                            placeholder="Select a customer"
                            search-placeholder="Search by name, organization or number…"
                            :empty-text="
                                searchingCustomers
                                    ? 'Searching…'
                                    : 'No customer matches that. Use New customer to register them.'
                            "
                            @search="searchCustomers"
                        />
                        <p
                            v-if="hasMoreCustomers"
                            class="text-muted-foreground text-sm"
                            data-test="more-customers-hint"
                        >
                            Showing the first {{ customers.length }} customers.
                            Type a name, organization or number to find the
                            rest.
                        </p>
                        <InputError :message="form.errors.customer_id" />
                    </div>

                    <div v-else class="grid gap-4 @2xl:grid-cols-2">
                        <div class="grid content-start gap-2">
                            <Label for="customer-name">Name</Label>
                            <Input
                                id="customer-name"
                                v-model="form.customer.name"
                                autocomplete="off"
                            />
                            <InputError
                                :message="form.errors['customer.name']"
                            />
                        </div>
                        <div class="grid content-start gap-2">
                            <Label for="customer-organization">
                                Organization (optional)
                            </Label>
                            <Input
                                id="customer-organization"
                                v-model="form.customer.organization"
                                autocomplete="off"
                            />
                            <InputError
                                :message="form.errors['customer.organization']"
                            />
                        </div>
                        <div class="grid content-start gap-2">
                            <Label for="customer-contact-number">
                                Contact number
                            </Label>
                            <Input
                                id="customer-contact-number"
                                v-model="form.customer.contact_number"
                                autocomplete="off"
                            />
                            <InputError
                                :message="
                                    form.errors['customer.contact_number']
                                "
                            />
                        </div>
                        <div class="grid content-start gap-2">
                            <Label for="customer-email">Email</Label>
                            <Input
                                id="customer-email"
                                v-model="form.customer.email"
                                type="email"
                                autocomplete="off"
                            />
                            <InputError
                                :message="form.errors['customer.email']"
                            />
                        </div>
                        <div class="grid content-start gap-2 @2xl:col-span-2">
                            <Label for="customer-address">Address</Label>
                            <Textarea
                                id="customer-address"
                                v-model="form.customer.address"
                                rows="2"
                            />
                            <InputError
                                :message="form.errors['customer.address']"
                            />
                        </div>
                    </div>
                </CardContent>
            </Card>

            <JobOrderRowFields
                v-for="(row, index) in form.job_orders"
                :key="row._key"
                :row="row"
                :index="index"
                :errors="jobOrderRowErrors(form.errors, index)"
                :pricing-entries="pricingEntries"
                :specification-options="specificationOptions"
                :print-size-dimensions="printSizeDimensions"
                :rush-fee-percentage="rushFeePercentage"
                :accepted-file-formats="acceptedFileFormats"
                :removable="form.job_orders.length > 1"
                @remove="removeRow(index)"
            />
            <Button
                type="button"
                variant="secondary"
                class="self-start"
                data-test="add-job-order-row-button"
                @click="addRow"
            >
                <Plus class="size-4" />
                Add Another Job Order
            </Button>
        </div>

        <div
            class="bg-background/95 border-border sticky bottom-0 z-10 w-full border backdrop-blur max-sm:group-has-[input:focus]:static max-sm:group-has-[textarea:focus]:static"
        >
            <div
                class="mx-auto flex w-full max-w-[100rem] flex-col gap-2 px-4 pt-3 pb-[calc(0.75rem+env(safe-area-inset-bottom))] sm:flex-row sm:items-center sm:justify-between sm:gap-3 sm:px-6 lg:px-8"
            >
                <p
                    class="text-muted-foreground text-xs tabular-nums sm:text-sm"
                >
                    {{ form.job_orders.length }} job order{{
                        form.job_orders.length === 1 ? '' : 's'
                    }}
                    · Estimated total {{ money(estimatedTotal) }}
                </p>
                <Button
                    type="button"
                    size="lg"
                    class="w-full shrink-0 sm:w-auto"
                    :disabled="form.processing"
                    data-test="create-job-order-button"
                    @click="submit"
                >
                    <Ticket class="size-4" />
                    {{ form.processing ? 'Creating…' : 'Create Job Order' }}
                </Button>
            </div>
        </div>
    </PageContainer>
</template>

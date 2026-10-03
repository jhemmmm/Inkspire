<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { Plus, Ticket, UserPlus, Users } from '@lucide/vue';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import JobOrderIntakeController from '@/actions/App/Http/Controllers/Artist/JobOrderIntakeController';
import InputError from '@/components/InputError.vue';
import JobOrderRowFields, {
    emptyJobOrderRow,
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
    customers: CustomerOption[];
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

const customerOptions = computed(() =>
    props.customers.map((customer) => ({
        value: String(customer.id),
        label: customer.name,
        hint: [customer.organization, customer.contact_number]
            .filter(Boolean)
            .join(' · '),
    })),
);

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

function jobOrderRowErrors(index: number): Record<string, string | undefined> {
    const prefix = `job_orders.${index}.`;
    const sliced: Record<string, string | undefined> = {};

    for (const [key, value] of Object.entries(form.errors)) {
        if (key.startsWith(prefix)) {
            sliced[key.slice(prefix.length)] = value as string | undefined;
        }
    }

    return sliced;
}

const estimatedTotal = computed(() =>
    form.job_orders.reduce(
        (sum: number, row: JobOrderRow) =>
            sum + (rowLineAmount(row, props.pricingEntries) ?? 0),
        0,
    ),
);

function submit(): void {
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

    <PageContainer>
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
                        empty-text="No customer matches that. Use New customer to register them."
                    />
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
                        <InputError :message="form.errors['customer.name']" />
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
                            :message="form.errors['customer.contact_number']"
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
                        <InputError :message="form.errors['customer.email']" />
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
            :errors="jobOrderRowErrors(index)"
            :pricing-entries="pricingEntries"
            :specification-options="specificationOptions"
            :print-size-dimensions="printSizeDimensions"
            :rush-fee-percentage="rushFeePercentage"
            :accepted-file-formats="acceptedFileFormats"
            :removable="form.job_orders.length > 1"
            @remove="removeRow(index)"
        />

        <div
            class="bg-background/95 border-border sticky bottom-0 -mx-4 flex flex-wrap items-center justify-between gap-3 border-t px-4 py-4 backdrop-blur sm:-mx-6 sm:px-6 lg:-mx-8 lg:px-8"
        >
            <Button
                type="button"
                variant="secondary"
                data-test="add-job-order-row-button"
                @click="addRow"
            >
                <Plus class="size-4" />
                Add Another Job Order
            </Button>
            <div class="flex items-center gap-4">
                <p class="text-muted-foreground text-sm tabular-nums">
                    {{ form.job_orders.length }} job order{{
                        form.job_orders.length === 1 ? '' : 's'
                    }}
                    · Estimated total {{ money(estimatedTotal) }}
                </p>
                <Button
                    type="button"
                    size="lg"
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

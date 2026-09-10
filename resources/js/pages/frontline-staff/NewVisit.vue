<script setup lang="ts">
import { Form, Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    CalendarDays,
    Check,
    CloudUpload,
    FileText,
    Hash,
    PencilRuler,
    Plus,
    Printer,
    Search,
    Settings,
    Ticket,
    User,
    UserPlus,
    X,
    Zap,
} from '@lucide/vue';
import { computed, nextTick, ref, watch } from 'vue';
import CustomerController from '@/actions/App/Http/Controllers/FrontlineStaff/CustomerController';
import QueueEntryController from '@/actions/App/Http/Controllers/FrontlineStaff/QueueEntryController';
import AlertError from '@/components/AlertError.vue';
import InputError from '@/components/InputError.vue';
import DataTableCard from '@/components/DataTableCard.vue';
import EmptyState from '@/components/EmptyState.vue';
import PageContainer from '@/components/PageContainer.vue';
import PageHeader from '@/components/PageHeader.vue';
import ReplaceJobOrderFileDialog from '@/components/ReplaceJobOrderFileDialog.vue';
import SearchableSelect, {
    type SearchableOption,
} from '@/components/SearchableSelect.vue';
import SectionHeading from '@/components/SectionHeading.vue';
import TrackingQrCode from '@/components/TrackingQrCode.vue';
import { Badge } from '@/components/ui/badge';
import { Button, buttonVariants } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
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
import { Textarea } from '@/components/ui/textarea';
import { frontlineStaffNavItems } from '@/config/nav/frontline-staff';
import { newVisit } from '@/routes/frontline-staff';

interface CustomerRecord {
    id: number;
    name: string;
    organization: string | null;
    contact_number: string;
    email: string;
    address: string;
}

interface PricingEntry {
    id: number;
    name: string;
    base_price: string;
    unit: string | null;
}

interface JobOrderRow {
    description: string;
    pricing_entry_id: string;
    client_notes: string;
    type: 'type_a' | 'type_b';
    print_size: string;
    material: string;
    quantity: string;
    deadline: string;
    is_rush: boolean;
    file: File | null;
    _key: string;
}

interface ConfirmedJobOrder {
    id: number;
    number: string | null;
    description: string;
    type: string;
    status: string;
    tracking_token: string;
    validation_failure_reason: string | null;
    assigned_artist: { id: number; name: string } | null;
}

interface CustomerJobOrder {
    id: number;
    number: string | null;
    description: string;
    type: string;
    status: string;
    created_at: string;
}

interface ConfirmedQueueEntry {
    id: number;
    queue_number: number;
    status: string;
    job_orders: ConfirmedJobOrder[];
}

const props = defineProps<{
    customers: CustomerRecord[];
    specificationOptions: Record<string, string[]>;
    pricingEntries: PricingEntry[];
    filters: { q?: string };
    selectedCustomer: CustomerRecord | null;
    customerJobOrders: CustomerJobOrder[];
    confirmedQueueEntry: ConfirmedQueueEntry | null;
    trackingBaseUrl: string;
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

// Registration used to be reachable only when a search found nobody at all,
// so looking up "Santos", finding Maria and needing Pedro left no way in.
const registerRequested = ref(false);

const showRegisterForm = computed(
    () =>
        registerRequested.value ||
        (hasSearched.value && props.customers.length === 0),
);

function openRegisterForm(): void {
    registerRequested.value = true;

    // The form can land well below the fold on a page already showing results,
    // so move to it rather than letting it appear silently.
    nextTick(() => {
        const field = document.getElementById('name');
        field?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        field?.focus({ preventScroll: true });
    });
}

function closeRegisterForm(): void {
    registerRequested.value = false;
}

// Set once the staff member asks for the intake form on a returning customer.
// Reset by the watch below so switching customers re-arms the gate rather than
// leaving the previous customer's form open.
const newJobOrderRequested = ref(false);

// A first-time customer has nothing to show, so they drop straight into the
// form with no extra click; a returning one sees their history first.
const showJobOrderForm = computed(
    () => newJobOrderRequested.value || props.customerJobOrders.length === 0,
);

// Inertia can preserve this component instance across the post-registration
// redirect instead of remounting it — keep `selected` in sync when that happens.
watch(
    () => props.selectedCustomer,
    (value) => {
        selected.value = value;
        newJobOrderRequested.value = false;
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

// Round-trips through the server rather than setting `selected` locally,
// because the customer's job order history is a server prop and a purely
// client-side selection would leave it stale. `selected` is deliberately NOT
// set optimistically here: doing so flashes the intake form for one frame
// before the history arrives and replaces it.
function selectCustomer(customer: CustomerRecord): void {
    router.get(
        newVisit.url(),
        { q: searchTerm.value, customer: customer.id },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

// Picking the wrong customer used to be unrecoverable without a page reload:
// nothing ever cleared `selected` short of the post-submit "Start New Visit"
// link.
//
// Dropping `customer` from the URL is the load-bearing half. Without it,
// re-picking the SAME customer produces an identical `props.selectedCustomer`,
// the watch above never fires, and the page sits stuck on the empty state.
function clearCustomer(): void {
    selected.value = null;

    router.get(
        newVisit.url(),
        { q: searchTerm.value },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

const STEPS = ['Customer', 'Job Orders', 'Queue Number'] as const;

const currentStep = computed(() => {
    if (!selected.value) {
        return 1;
    }

    return props.confirmedQueueEntry ? 3 : 2;
});

function emptyJobOrderRow(): JobOrderRow {
    return {
        description: '',
        pricing_entry_id: '',
        client_notes: '',
        type: 'type_a',
        print_size: '',
        material: '',
        quantity: '',
        deadline: '',
        is_rush: false,
        file: null,
        _key: crypto.randomUUID(),
    };
}

const printSizes = computed(() => props.specificationOptions.print_size ?? []);
const materials = computed(() => props.specificationOptions.material ?? []);

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

const ACCEPTED_FILE_TYPES = '.pdf,.ai,.psd,.png,.jpg,.jpeg,.cdr';

/**
 * Selecting a service sets the catalog id *and* mirrors its name into
 * `description`. The id is what the Cashier's pricing step reads; the name is
 * what every existing screen -- queue list, production board, receipt --
 * already displays.
 */
function selectService(row: JobOrderRow, value: unknown): void {
    const id = String(value ?? '');
    row.pricing_entry_id = id;
    row.description =
        props.pricingEntries.find((entry) => String(entry.id) === id)?.name ??
        '';
}

const serviceOptions = computed<SearchableOption[]>(() =>
    props.pricingEntries.map((entry) => ({
        value: String(entry.id),
        label: entry.name,
        hint: servicePriceLabel(entry),
    })),
);

const printSizeOptions = computed<SearchableOption[]>(() =>
    printSizes.value.map((size) => ({ value: size, label: size })),
);

const materialOptions = computed<SearchableOption[]>(() =>
    materials.value.map((material) => ({ value: material, label: material })),
);

function servicePriceLabel(entry: PricingEntry): string {
    const price = Number(entry.base_price).toLocaleString('en-PH', {
        minimumFractionDigits: 2,
    });

    return entry.unit ? `₱${price} / ${entry.unit}` : `₱${price}`;
}

const jobOrderTypeOptions = [
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
];

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

function onFileDrop(row: JobOrderRow, event: DragEvent): void {
    row.file = event.dataTransfer?.files?.[0] ?? null;
}

function formatFileSize(bytes: number): string {
    const megabytes = bytes / 1024 / 1024;

    return megabytes < 1
        ? `${Math.max(1, Math.round(bytes / 1024))} KB`
        : `${megabytes.toFixed(1)} MB`;
}

function selectJobOrderType(row: JobOrderRow, value: unknown): void {
    row.type = value === 'type_b' ? 'type_b' : 'type_a';
    if (row.type !== 'type_a') {
        row.file = null;
    }
}

function submitIntake(): void {
    intakeForm.post(QueueEntryController.store().url, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => intakeForm.reset(),
    });
}

// Which slip is currently being printed, so the others can be hidden. Both
// class strings below appear as literals in this file on purpose — Tailwind's
// scanner only generates classes it can see in the source.
const printingJobOrderId = ref<number | null>(null);

function slipPrintClass(jobOrderId: number): string {
    return printingJobOrderId.value !== null &&
        printingJobOrderId.value !== jobOrderId
        ? 'print:hidden'
        : '';
}

function trackingUrlFor(jobOrder: ConfirmedJobOrder): string {
    return `${props.trackingBaseUrl}/${jobOrder.tracking_token}`;
}

/**
 * Print one slip on its own.
 *
 * The `afterprint` reset is registered BEFORE window.print() rather than after
 * it: window.print() returns immediately in some browsers and blocks until the
 * preview closes in others, so a post-call reset races the render in the first
 * case and the next print would show every slip. nextTick() in between lets
 * the class binding actually reach the DOM before the preview snapshots it.
 */
async function printSlip(jobOrderId: number): Promise<void> {
    printingJobOrderId.value = jobOrderId;

    await nextTick();

    window.addEventListener(
        'afterprint',
        () => {
            printingJobOrderId.value = null;
        },
        { once: true },
    );

    window.print();
}

function formatSlipDate(value: string): string {
    return new Date(value).toLocaleDateString('en-PH', {
        year: 'numeric',
        month: 'short',
        day: '2-digit',
    });
}

function jobOrderTypeLabel(type: string): string {
    return type === 'type_a' ? 'Type A' : 'Type B';
}

// The four stages that actually mean "on the press". Enumerated rather
// than left as a catch-all v-else, which labelled in_consultation /
// in_design / pending_review / design_approved job orders "In Production".
const PRODUCTION_STATUSES = [
    'for_production',
    'printing',
    'quality_check',
    'ready_for_pickup',
];

function isInProduction(status: string): boolean {
    return PRODUCTION_STATUSES.includes(status);
}

function jobOrderStatusLabel(status: string): string {
    switch (status) {
        case 'ready_for_production':
            return 'Ready for Production';
        case 'assigned':
            return 'Assigned';
        case 'validation_failed':
            return 'Validation Failed';
        default:
            return 'Waiting for an Artist';
    }
}
</script>

<template>
    <Head title="New Visit" />

    <PageContainer>
        <PageHeader
            class="print:hidden"
            title="New Visit"
            description="Find or register the customer, capture what they are having printed, then hand them their queue number."
        />

        <!--
            The three phases of this page were previously one undifferentiated
            column, so a staff member mid-visit had no way to tell how much was
            left. An ordered list keeps that legible to screen readers too.
        -->
        <ol class="flex flex-wrap items-center gap-x-3 gap-y-2 print:hidden">
            <li
                v-for="(step, stepIndex) in STEPS"
                :key="step"
                class="flex items-center gap-3"
                :aria-current="
                    currentStep === stepIndex + 1 ? 'step' : undefined
                "
            >
                <span class="flex items-center gap-2">
                    <span
                        class="flex size-7 shrink-0 items-center justify-center rounded-full text-xs font-bold"
                        :class="
                            currentStep > stepIndex + 1
                                ? 'bg-primary text-primary-foreground'
                                : currentStep === stepIndex + 1
                                  ? 'border-primary text-primary border-2'
                                  : 'bg-muted text-muted-foreground'
                        "
                    >
                        <Check
                            v-if="currentStep > stepIndex + 1"
                            class="size-4"
                        />
                        <template v-else>{{ stepIndex + 1 }}</template>
                    </span>
                    <span
                        class="text-sm font-semibold"
                        :class="
                            currentStep >= stepIndex + 1
                                ? 'text-foreground'
                                : 'text-muted-foreground'
                        "
                    >
                        {{ step }}
                    </span>
                </span>
                <span
                    v-if="stepIndex < STEPS.length - 1"
                    class="bg-border hidden h-px w-8 sm:block"
                    aria-hidden="true"
                />
            </li>
        </ol>

        <template v-if="!selected">
            <Card>
                <CardHeader :icon="Search">
                    <CardTitle>Find the customer</CardTitle>
                </CardHeader>
                <CardContent>
                    <form
                        class="flex flex-col gap-4 sm:flex-row sm:items-end"
                        @submit.prevent="search"
                    >
                        <div class="flex min-w-0 flex-1 flex-col gap-2">
                            <Label for="customer-search">
                                Search Customers
                            </Label>
                            <Input
                                id="customer-search"
                                v-model="searchTerm"
                                type="search"
                                class="w-full sm:max-w-md"
                                placeholder="Name, organization or contact number"
                            />
                            <p
                                v-if="showEmptyQueryError"
                                class="text-destructive text-sm"
                                role="alert"
                            >
                                Enter a name or contact number to search.
                            </p>
                        </div>
                        <div class="flex flex-wrap gap-2 sm:mb-0.5">
                            <Button
                                type="submit"
                                data-test="search-customers-button"
                            >
                                <Search class="size-4" />
                                Search Customers
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                data-test="open-register-customer-button"
                                @click="openRegisterForm"
                            >
                                <UserPlus class="size-4" />
                                Register New Customer
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>

            <DataTableCard v-if="hasSearched">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Name</TableHead>
                            <TableHead>Organization</TableHead>
                            <TableHead>Contact Number</TableHead>
                            <TableHead>Email</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableEmpty v-if="customers.length === 0" :colspan="4">
                            <EmptyState
                                :icon="UserPlus"
                                title="No matching customers"
                                description="No customer matches this name or contact number. Register a new customer below to continue."
                            />
                        </TableEmpty>
                        <TableRow
                            v-for="customer in customers"
                            v-else
                            :key="customer.id"
                            class="hover:bg-accent/50 cursor-pointer"
                            tabindex="0"
                            :data-test="`select-customer-${customer.id}-row`"
                            @click="selectCustomer(customer)"
                            @keyup.enter="selectCustomer(customer)"
                        >
                            <TableCell>{{ customer.name }}</TableCell>
                            <TableCell class="text-muted-foreground">
                                {{ customer.organization || '—' }}
                            </TableCell>
                            <TableCell>{{ customer.contact_number }}</TableCell>
                            <TableCell>{{ customer.email }}</TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </DataTableCard>

            <Card v-if="showRegisterForm" data-test="register-customer-card">
                <CardHeader :icon="UserPlus">
                    <CardTitle>Register New Customer</CardTitle>
                </CardHeader>
                <CardContent>
                    <Form
                        v-bind="CustomerController.store.form()"
                        class="grid gap-6 md:grid-cols-2"
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
                            <Label for="organization">
                                Organization
                                <span class="text-muted-foreground font-normal">
                                    (optional)
                                </span>
                            </Label>
                            <Input
                                id="organization"
                                name="organization"
                                placeholder="Company, school or agency"
                            />
                            <InputError :message="errors.organization" />
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

                        <div class="grid gap-2 md:col-span-2">
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

                        <div
                            class="flex flex-wrap items-center gap-2 md:col-span-2"
                        >
                            <Button
                                type="submit"
                                :disabled="processing"
                                data-test="register-customer-button"
                            >
                                <UserPlus class="size-4" />
                                Register New Customer
                            </Button>
                            <!--
                                Offered only when the form was opened on
                                purpose. When it opened because a search found
                                nobody, closing it would leave the staff member
                                with results they already know are wrong and no
                                way forward.
                            -->
                            <Button
                                v-if="registerRequested"
                                type="button"
                                variant="ghost"
                                data-test="cancel-register-customer-button"
                                @click="closeRegisterForm"
                            >
                                Cancel
                            </Button>
                        </div>
                    </Form>
                </CardContent>
            </Card>
        </template>

        <!--
            Once a customer is chosen their details are reference material, not
            the task -- so this is a one-line bar rather than the full card it
            used to be, keeping the job order form above the fold.
        -->
        <div
            v-else
            class="bg-card border-border flex flex-wrap items-center gap-x-4 gap-y-3 rounded-xl border p-4 shadow-sm print:hidden"
        >
            <span
                class="bg-accent text-accent-foreground flex size-9 shrink-0 items-center justify-center rounded-lg"
            >
                <User class="size-[18px]" />
            </span>
            <div class="flex min-w-0 flex-1 flex-col">
                <p class="truncate font-semibold">
                    {{ selected.name }}
                    <span
                        v-if="selected.organization"
                        class="text-muted-foreground font-normal"
                    >
                        · {{ selected.organization }}
                    </span>
                </p>
                <p class="text-muted-foreground truncate text-sm">
                    {{ selected.contact_number }} · {{ selected.email }}
                </p>
            </div>
            <Button
                v-if="!confirmedQueueEntry"
                type="button"
                variant="outline"
                size="sm"
                data-test="change-customer-button"
                @click="clearCustomer"
            >
                Change
            </Button>
        </div>

        <Card
            v-if="selected && confirmedQueueEntry"
            class="print:border-0 print:shadow-none"
            data-test="queue-confirmation-card"
        >
            <CardHeader :icon="Ticket" class="print:hidden">
                <CardTitle>Queue Number</CardTitle>
            </CardHeader>
            <CardContent class="flex flex-col gap-6">
                <div class="flex items-center gap-4 print:hidden">
                    <p
                        class="bg-primary text-primary-foreground flex size-20 shrink-0 items-center justify-center rounded-2xl text-4xl leading-none font-extrabold tabular-nums"
                    >
                        {{ confirmedQueueEntry.queue_number }}
                    </p>
                    <div class="flex min-w-0 flex-col gap-1">
                        <p class="font-semibold">
                            Give this number to {{ selected.name }}
                        </p>
                        <p class="text-muted-foreground text-sm">
                            {{ confirmedQueueEntry.job_orders.length }} job
                            order(s) queued.
                        </p>
                    </div>
                </div>

                <ul class="divide-border flex flex-col divide-y print:hidden">
                    <template
                        v-for="jobOrder in confirmedQueueEntry.job_orders"
                        :key="jobOrder.id"
                    >
                        <li
                            class="flex flex-wrap items-center justify-between gap-x-4 gap-y-2 py-3 first:pt-0"
                        >
                            <span class="min-w-0 font-medium">
                                {{ jobOrder.description }}
                            </span>
                            <div class="flex flex-wrap items-center gap-2">
                                <Badge variant="outline">
                                    {{ jobOrderTypeLabel(jobOrder.type) }}
                                </Badge>
                                <Badge
                                    v-if="jobOrder.status === 'intake'"
                                    variant="outline"
                                >
                                    {{ jobOrderStatusLabel(jobOrder.status) }}
                                </Badge>
                                <Badge
                                    v-else-if="
                                        jobOrder.status ===
                                        'ready_for_production'
                                    "
                                    class="text-green-600 dark:text-green-400"
                                >
                                    {{ jobOrderStatusLabel(jobOrder.status) }}
                                </Badge>
                                <Badge
                                    v-else-if="jobOrder.status === 'assigned'"
                                    variant="default"
                                >
                                    {{ jobOrderStatusLabel(jobOrder.status) }}
                                </Badge>
                                <Badge
                                    v-else-if="
                                        jobOrder.status === 'validation_failed'
                                    "
                                    variant="destructive"
                                >
                                    {{ jobOrderStatusLabel(jobOrder.status) }}
                                </Badge>
                                <Badge
                                    v-else-if="isInProduction(jobOrder.status)"
                                    variant="secondary"
                                >
                                    In Production
                                </Badge>
                                <Badge v-else variant="secondary">
                                    In Design
                                </Badge>
                            </div>
                        </li>
                        <p
                            v-if="jobOrder.status === 'assigned'"
                            class="text-muted-foreground text-sm"
                        >
                            Assigned to
                            {{ jobOrder.assigned_artist?.name }}
                        </p>
                        <template
                            v-if="jobOrder.status === 'validation_failed'"
                        >
                            <AlertError
                                :errors="[
                                    jobOrder.validation_failure_reason ?? '',
                                ]"
                                title="Validation Failed"
                            />
                            <ReplaceJobOrderFileDialog
                                :job-order-id="jobOrder.id"
                            >
                                <Button
                                    variant="outline"
                                    size="sm"
                                    data-test="replace-file-button"
                                >
                                    Replace File
                                </Button>
                            </ReplaceJobOrderFileDialog>
                        </template>
                    </template>
                </ul>

                <section class="flex flex-col gap-4">
                    <div class="flex flex-col gap-1 print:hidden">
                        <h3 class="font-semibold">Customer Slips</h3>
                        <p class="text-muted-foreground text-sm">
                            Print one slip per job order and hand it over with
                            the queue number. Scanning it opens a page showing
                            where the order is up to.
                        </p>
                    </div>

                    <div
                        v-for="jobOrder in confirmedQueueEntry.job_orders"
                        :key="`slip-${jobOrder.id}`"
                        class="border-border flex flex-col items-center gap-3 rounded-xl border p-6 text-center print:break-inside-avoid"
                        :class="slipPrintClass(jobOrder.id)"
                        :data-test="`job-order-slip-${jobOrder.id}`"
                    >
                        <TrackingQrCode
                            :tracking-url="trackingUrlFor(jobOrder)"
                        />
                        <p class="text-lg font-bold tabular-nums">
                            {{ jobOrder.number ?? '—' }}
                        </p>
                        <p class="font-medium">{{ selected.name }}</p>
                        <p class="text-muted-foreground text-sm">
                            Scan this code to follow your order.
                        </p>
                        <Button
                            type="button"
                            variant="outline"
                            class="print:hidden"
                            :data-test="`print-slip-${jobOrder.id}-button`"
                            @click="printSlip(jobOrder.id)"
                        >
                            <Printer class="size-4" />
                            Print Slip
                        </Button>
                    </div>
                </section>

                <Link
                    :href="newVisit()"
                    :class="buttonVariants({ variant: 'secondary' })"
                    class="self-start print:hidden"
                    data-test="start-new-visit-link"
                >
                    Start New Visit
                </Link>
            </CardContent>
        </Card>

        <template v-if="selected && !confirmedQueueEntry && !showJobOrderForm">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <SectionHeading
                    title="Previous Job Orders"
                    description="This customer's most recent orders. Start a new one at any time."
                />
                <Button
                    size="lg"
                    type="button"
                    data-test="new-job-order-button"
                    @click="newJobOrderRequested = true"
                >
                    <Plus class="size-4" />
                    New Job Order
                </Button>
            </div>

            <DataTableCard>
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Job Order</TableHead>
                            <TableHead>Description</TableHead>
                            <TableHead>Type</TableHead>
                            <TableHead>Status</TableHead>
                            <TableHead>Date</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow
                            v-for="jobOrder in customerJobOrders"
                            :key="jobOrder.id"
                            :data-test="`customer-job-order-${jobOrder.id}-row`"
                        >
                            <TableCell class="tabular-nums">
                                {{ jobOrder.number ?? '—' }}
                            </TableCell>
                            <TableCell>{{ jobOrder.description }}</TableCell>
                            <TableCell>
                                <Badge variant="outline">
                                    {{ jobOrderTypeLabel(jobOrder.type) }}
                                </Badge>
                            </TableCell>
                            <TableCell class="text-muted-foreground">
                                {{ jobOrderStatusLabel(jobOrder.status) }}
                            </TableCell>
                            <TableCell
                                class="text-muted-foreground tabular-nums"
                            >
                                {{ formatSlipDate(jobOrder.created_at) }}
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </DataTableCard>

            <!--
                Repeated below the table so the primary action is never a
                screen away once a customer has a long history.
            -->
            <div class="flex">
                <Button
                    size="lg"
                    type="button"
                    data-test="new-job-order-below-button"
                    @click="newJobOrderRequested = true"
                >
                    <Plus class="size-4" />
                    New Job Order
                </Button>
            </div>
        </template>

        <template v-if="selected && !confirmedQueueEntry && showJobOrderForm">
            <Card v-for="(row, index) in intakeForm.job_orders" :key="row._key">
                <CardHeader :icon="FileText">
                    <div class="flex items-center justify-between gap-2">
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
                    </div>
                </CardHeader>
                <CardContent class="grid gap-6">
                    <div class="grid gap-2">
                        <Label :for="`job-order-service-${index}`">
                            Product / Service
                        </Label>
                        <SearchableSelect
                            :id="`job-order-service-${index}`"
                            :model-value="row.pricing_entry_id"
                            :options="serviceOptions"
                            placeholder="Search the price list…"
                            empty-text="No service matches that search."
                            @update:model-value="
                                (value) => selectService(row, value)
                            "
                        />
                        <InputError
                            :message="
                                intakeForm.errors[
                                    `job_orders.${index}.description`
                                ] ??
                                intakeForm.errors[
                                    `job_orders.${index}.pricing_entry_id`
                                ]
                            "
                        />
                    </div>

                    <fieldset class="grid gap-2">
                        <legend class="sr-only">Job Order Type</legend>
                        <p class="text-sm font-medium">Job Order Type</p>
                        <div class="grid gap-4 md:grid-cols-2">
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
                                    @change="
                                        selectJobOrderType(row, option.value)
                                    "
                                />
                                <span
                                    class="bg-muted text-muted-foreground peer-checked:bg-primary peer-checked:text-primary-foreground flex size-9 shrink-0 items-center justify-center rounded-lg transition-colors"
                                >
                                    <component
                                        :is="option.icon"
                                        class="size-[18px]"
                                    />
                                </span>
                                <span
                                    class="flex min-w-0 flex-1 flex-col gap-1"
                                >
                                    <span
                                        class="flex items-start justify-between gap-2"
                                    >
                                        <span class="font-semibold">
                                            {{ option.title }}
                                        </span>
                                        <Badge
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
                        <InputError
                            :message="
                                intakeForm.errors[`job_orders.${index}.type`]
                            "
                        />
                    </fieldset>

                    <section
                        class="border-border overflow-hidden rounded-xl border"
                    >
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

                        <div class="grid gap-6 p-6 md:grid-cols-2">
                            <div class="grid gap-2">
                                <Label :for="`job-order-print-size-${index}`">
                                    Print Size
                                </Label>
                                <SearchableSelect
                                    :id="`job-order-print-size-${index}`"
                                    v-model="row.print_size"
                                    :options="printSizeOptions"
                                    placeholder="Search print sizes…"
                                    empty-text="No print size matches that search."
                                />
                                <InputError
                                    :message="
                                        intakeForm.errors[
                                            `job_orders.${index}.print_size`
                                        ]
                                    "
                                />
                            </div>

                            <div class="grid gap-2">
                                <Label :for="`job-order-quantity-${index}`">
                                    Quantity
                                </Label>
                                <div class="relative">
                                    <Hash
                                        class="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2"
                                    />
                                    <Input
                                        :id="`job-order-quantity-${index}`"
                                        v-model="row.quantity"
                                        type="number"
                                        min="1"
                                        class="pl-9"
                                        placeholder="Quantity"
                                    />
                                </div>
                                <InputError
                                    :message="
                                        intakeForm.errors[
                                            `job_orders.${index}.quantity`
                                        ]
                                    "
                                />
                            </div>

                            <div class="grid gap-2">
                                <Label :for="`job-order-material-${index}`">
                                    Material
                                </Label>
                                <SearchableSelect
                                    :id="`job-order-material-${index}`"
                                    v-model="row.material"
                                    :options="materialOptions"
                                    placeholder="Search materials…"
                                    empty-text="No material matches that search."
                                />
                                <InputError
                                    :message="
                                        intakeForm.errors[
                                            `job_orders.${index}.material`
                                        ]
                                    "
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
                                <InputError
                                    :message="
                                        intakeForm.errors[
                                            `job_orders.${index}.deadline`
                                        ]
                                    "
                                />
                            </div>

                            <div class="grid gap-2 md:col-span-2">
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
                                        Rush Order
                                    </Label>
                                </div>
                                <p class="text-muted-foreground text-sm">
                                    Prioritised in production. The Cashier
                                    decides whether the rush fee is charged.
                                </p>
                                <InputError
                                    :message="
                                        intakeForm.errors[
                                            `job_orders.${index}.is_rush`
                                        ]
                                    "
                                />
                            </div>

                            <div class="grid gap-2 md:col-span-2">
                                <Label :for="`job-order-notes-${index}`">
                                    {{
                                        row.type === 'type_b'
                                            ? 'Client Instructions'
                                            : 'Notes'
                                    }}
                                    <span
                                        class="text-muted-foreground font-normal"
                                    >
                                        (optional)
                                    </span>
                                </Label>
                                <Textarea
                                    :id="`job-order-notes-${index}`"
                                    v-model="row.client_notes"
                                    rows="4"
                                    :placeholder="
                                        row.type === 'type_b'
                                            ? 'What the customer wants: colours, wording, references, questions they asked…'
                                            : 'Anything the customer mentioned about this print.'
                                    "
                                />
                                <p class="text-muted-foreground text-sm">
                                    {{
                                        row.type === 'type_b'
                                            ? 'The artist sees this as their brief before the consultation.'
                                            : 'Passed along with the job order.'
                                    }}
                                </p>
                                <InputError
                                    :message="
                                        intakeForm.errors[
                                            `job_orders.${index}.client_notes`
                                        ]
                                    "
                                />
                            </div>

                            <div
                                v-if="row.type === 'type_a'"
                                class="grid gap-2 md:col-span-2"
                            >
                                <Label :for="`job-order-file-${index}`">
                                    Source File
                                </Label>
                                <label
                                    :class="[
                                        'border-border bg-muted/30 hover:border-primary hover:bg-accent/40 has-[:focus-visible]:border-ring has-[:focus-visible]:ring-ring/50 flex cursor-pointer flex-col items-center gap-2 rounded-xl border-2 border-dashed px-6 py-10 text-center transition-colors has-[:focus-visible]:ring-[3px]',
                                    ]"
                                    :data-test="`job-order-${index}-dropzone`"
                                    @dragover.prevent
                                    @drop.prevent="onFileDrop(row, $event)"
                                >
                                    <input
                                        :id="`job-order-file-${index}`"
                                        type="file"
                                        class="sr-only"
                                        :accept="ACCEPTED_FILE_TYPES"
                                        @change="onFileChange(row, $event)"
                                    />
                                    <CloudUpload
                                        class="text-muted-foreground size-7"
                                    />
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
                                                : 'Accepted: PDF, AI, PSD, PNG, JPG, CDR'
                                        }}
                                    </span>
                                    <span
                                        class="text-muted-foreground max-w-prose text-xs"
                                    >
                                        Checked against the print size above. If
                                        it is too low-resolution to print
                                        sharply at that size, an artist picks it
                                        up to improve it first.
                                    </span>
                                </label>
                                <InputError
                                    :message="
                                        intakeForm.errors[
                                            `job_orders.${index}.file`
                                        ]
                                    "
                                />
                            </div>
                        </div>
                    </section>
                </CardContent>
            </Card>

            <!--
                Sticky, because a visit with three job orders pushes these
                buttons a full screen below the last field and staff were
                scrolling back down to find them.
            -->
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
                <Button
                    type="button"
                    size="lg"
                    :disabled="intakeForm.processing"
                    data-test="add-to-queue-button"
                    @click="submitIntake"
                >
                    <Ticket class="size-4" />
                    {{
                        intakeForm.processing
                            ? 'Adding to queue…'
                            : 'Add to Queue'
                    }}
                </Button>
            </div>
        </template>
    </PageContainer>
</template>

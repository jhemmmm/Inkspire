<script setup lang="ts">
import type { FormComponentRef } from '@inertiajs/core';
import { Form, Head, router } from '@inertiajs/vue3';
import {
    Banknote,
    Clock,
    CreditCard,
    Landmark,
    Receipt,
    Smartphone,
    Wallet,
} from '@lucide/vue';
import { computed, nextTick, ref, useTemplateRef, watch } from 'vue';
import CreditRequestController from '@/actions/App/Http/Controllers/Cashier/CreditRequestController';
import PaymentController from '@/actions/App/Http/Controllers/Cashier/PaymentController';
import PaymentLinkController from '@/actions/App/Http/Controllers/Cashier/PaymentLinkController';
import ReconciliationController from '@/actions/App/Http/Controllers/Cashier/ReconciliationController';
import InputError from '@/components/InputError.vue';
import PageContainer from '@/components/PageContainer.vue';
import SearchableSelect, {
    type SearchableOption,
} from '@/components/SearchableSelect.vue';
import PageHeader from '@/components/PageHeader.vue';
import PricingSummary from '@/components/PricingSummary.vue';
import {
    AlertDialog,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
    AlertDialogTrigger,
} from '@/components/alert-dialog';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { Switch } from '@/components/ui/switch';
import { cashierNavItems } from '@/config/nav/cashier';
import { money, round2 } from '@/lib/jobOrders';
import { dashboard } from '@/routes/cashier';
import { reconcile } from '@/routes/cashier/job-orders';

interface PricingEntryOption {
    id: number;
    name: string;
    base_price: number;
}

interface JobOrderPaymentJobOrder {
    id: number;
    description: string;
    payment_status: string;
    total_amount: number | null;
    base_price_snapshot: number | null;
    quoted_amount: number | null;
    rush_fee_amount: number | null;
    is_rush: boolean;
    discount_amount: number | null;
    pricing_entry: PricingEntryOption | null;
}

const props = defineProps<{
    jobOrder: JobOrderPaymentJobOrder;
    pricingEntries: PricingEntryOption[];
    rushFeePercentage: number;
    discountCapPercentage: number;
    discountCapFlatAmount: number;
    pricingLocked: boolean;
    amountPaid: number;
    remainingBalance: number | null;
    pendingPaymongoAmount: number | null;
    pendingPaymongoMethod: 'gcash' | 'maya' | null;
}>();

defineOptions({
    layout: {
        navItems: cashierNavItems,
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: 'Job Order Payment', href: '#' },
        ],
    },
});

const pricingEntryId = ref<number | null>(
    props.jobOrder.pricing_entry?.id ?? null,
);
const lineAmount = ref<number>(
    Number(
        props.jobOrder.base_price_snapshot ?? props.jobOrder.quoted_amount ?? 0,
    ),
);
// A saved rush_fee_amount of 0 means the Cashier already looked at this job
// order and declined the fee — re-deriving the toggle from `is_rush` on a
// revisit would silently overturn that decision. So the staff-declared flag
// only supplies the DEFAULT, and only while this job order has never been
// priced (rush_fee_amount still null).
const rushFeeApplied = ref<boolean>(
    props.jobOrder.rush_fee_amount === null
        ? props.jobOrder.is_rush
        : Boolean(props.jobOrder.rush_fee_amount),
);
const discountType = ref<'' | 'percentage' | 'flat'>('');
const discountValue = ref<number | undefined>(undefined);

const paymentMethod = ref<
    'cash' | 'bank_transfer' | 'gcash' | 'maya' | 'on_credit'
>('cash');
const paymentType = ref<'full' | 'down'>('full');
/** Only for working out change; never submitted or stored. */
const cashReceived = ref<number | undefined>(undefined);
const referenceNumber = ref('');
const downPaymentAmount = ref<number | undefined>(undefined);

/**
 * Bank Transfer, GCash and Maya are all paid outside this app: the customer
 * sends the money, and the Cashier records the reference they show.
 */
const REFERENCE_LABELS: Partial<Record<typeof paymentMethod.value, string>> = {
    bank_transfer: 'Bank Reference Number',
    gcash: 'GCash Reference Number',
    maya: 'Maya Reference Number',
};

const referenceLabel = computed(() => REFERENCE_LABELS[paymentMethod.value]);

const isOnCredit = computed(() => paymentMethod.value === 'on_credit');

/**
 * The wallet of a checkout the customer opened from their own tracking page
 * and has not finished. Shown as a warning so the same money is not taken
 * twice.
 */
const pendingOnlineMethodLabel = computed(() =>
    props.pendingPaymongoMethod === 'gcash' ? 'GCash' : 'Maya',
);

const checkingPaymentStatus = ref(false);

/**
 * Ask PayMongo whether the customer's online checkout went through.
 *
 * A plain router.post() rather than a nested <Form> — this button lives
 * inside the page's single outer <Form> (Pricing + Payment submission),
 * and HTML forbids a <form> nested inside another <form>.
 */
function checkPaymentStatus(): void {
    checkingPaymentStatus.value = true;

    router.post(
        reconcile.url(props.jobOrder.id),
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                checkingPaymentStatus.value = false;
            },
        },
    );
}

const cancellingOnlinePayment = ref(false);

/**
 * Withdraw the customer's unfinished online checkout, so the payment can be
 * taken here instead. Until it is confirmed or withdrawn, every other way
 * of paying this job order is refused.
 */
function cancelOnlinePayment(): void {
    cancellingOnlinePayment.value = true;

    router.delete(ReconciliationController.destroy.url(props.jobOrder.id), {
        preserveScroll: true,
        onFinish: () => {
            cancellingOnlinePayment.value = false;
        },
    });
}

const hasPendingOnlinePayment = computed(
    () => props.pendingPaymongoAmount !== null,
);

const pricingOptions = computed<SearchableOption[]>(() =>
    props.pricingEntries.map((entry) => ({
        value: String(entry.id),
        label: entry.name,
        hint: `₱${Number(entry.base_price).toLocaleString('en-PH', {
            minimumFractionDigits: 2,
        })}`,
    })),
);

function onPricingEntryChange(value: unknown): void {
    const id = Number(value);
    pricingEntryId.value = id;

    const entry = props.pricingEntries.find((candidate) => candidate.id === id);
    if (entry) {
        lineAmount.value = Number(entry.base_price);
    }
}

/**
 * Display-only mirror of ComputeJobOrderPrice's exact formula — the server
 * recomputes and is authoritative on submit, this is a live preview only.
 */
const breakdown = computed(() => {
    const base = Number(lineAmount.value) || 0;
    const rushFeeAmount = rushFeeApplied.value
        ? round2((base * props.rushFeePercentage) / 100)
        : 0;
    const subtotal = base + rushFeeAmount;

    let discountAmount = 0;
    if (discountType.value === 'percentage') {
        discountAmount = round2(
            (subtotal * (Number(discountValue.value) || 0)) / 100,
        );
    } else if (discountType.value === 'flat') {
        discountAmount = Number(discountValue.value) || 0;
    }

    const total = Math.max(0, round2(subtotal - discountAmount));

    return { base, rushFeeAmount, discountAmount, total };
});

/**
 * The four figures the Pricing card totals up, from whichever source is
 * authoritative for this visit: the live form while the job order is still
 * being priced, the stored snapshot once it has been.
 *
 * `paid` is what the customer has already handed over across every earlier
 * transaction, so `remaining` is what they owe today — the number the
 * cashier reads out loud. On a first visit both are 0 and `remaining`
 * equals the total, which is exactly right.
 */
const pricingSummary = computed(() => {
    const priced = props.pricingLocked;

    const base = priced
        ? Number(props.jobOrder.base_price_snapshot ?? 0)
        : breakdown.value.base;
    const rushFeeAmount = priced
        ? Number(props.jobOrder.rush_fee_amount ?? 0)
        : breakdown.value.rushFeeAmount;
    const discountAmount = priced
        ? Number(props.jobOrder.discount_amount ?? 0)
        : breakdown.value.discountAmount;
    const total = priced
        ? Number(props.jobOrder.total_amount ?? 0)
        : breakdown.value.total;
    const paid = priced ? Number(props.amountPaid ?? 0) : 0;

    return {
        base,
        rushFeeAmount,
        discountAmount,
        total,
        paid,
        remaining: Math.max(0, round2(total - paid)),
    };
});

/**
 * The amount a "Full Payment" would settle right now — either the live
 * preview total (first pricing/payment visit) or the already-snapshotted
 * job order's remaining balance (a follow-up visit).
 */
const targetAmount = computed<number>(() =>
    props.pricingLocked ? (props.remainingBalance ?? 0) : breakdown.value.total,
);

/**
 * A cash down payment shows the change calculator only on request, so the
 * usual case (customer hands over the exact down payment) stays one field.
 */
const showDownPaymentChange = ref(false);

/** What the cash is paying for: the whole amount due, or the down payment. */
const cashPaysFor = computed<number>(() =>
    paymentType.value === 'down'
        ? Number(downPaymentAmount.value) || 0
        : targetAmount.value,
);

/** The cash typed in, as a number; blank reads as nothing handed over. */
const cashReceivedAmount = computed<number>(
    () => Number(cashReceived.value) || 0,
);

const cashHint = computed<string>(() => {
    const received = cashReceivedAmount.value;

    if (cashPaysFor.value <= 0) {
        return paymentType.value === 'down'
            ? 'Type the down payment amount first.'
            : 'Choose the product first, so there is an amount due.';
    }

    if (received <= 0) {
        return 'Type the cash the customer handed you to see their change.';
    }

    if (received < cashPaysFor.value) {
        const short = money(round2(cashPaysFor.value - received));

        return paymentType.value === 'down'
            ? `${short} short of the down payment.`
            : `${short} short. Paying only part now? Choose Down Payment.`;
    }

    return `Change: ${money(round2(received - cashPaysFor.value))}`;
});

// Following "Paying only part now? Choose Down Payment" carries the cash
// already typed across as the down payment, rather than hiding it and making
// the Cashier type it again.
watch(paymentType, (type) => {
    const received = cashReceivedAmount.value;

    if (
        type === 'down' &&
        !Number(downPaymentAmount.value) &&
        received > 0 &&
        received < targetAmount.value
    ) {
        downPaymentAmount.value = received;
    }
});

const downPaymentRemainingBalance = computed<number>(() =>
    round2(targetAmount.value - (Number(downPaymentAmount.value) || 0)),
);

const submitLabel = computed(() => {
    if (isOnCredit.value) {
        return 'Request On-Credit Approval';
    }

    return paymentType.value === 'down'
        ? 'Record Down Payment'
        : 'Record Payment';
});

const creditRequestingProcessing = ref(false);
const creditRequestDialogOpen = ref(false);

/**
 * A plain router.post() rather than a nested <Form> — the On Credit confirm
 * button lives inside the page's single outer <Form> (Pricing + Payment
 * submission), and HTML forbids a <form> nested inside another <form>. The
 * Pricing card fields are always included; CreditRequestController only
 * reads them when the job order hasn't been priced yet.
 */
function submitCreditRequest(): void {
    creditRequestingProcessing.value = true;
    paymentForm.value?.clearErrors();

    router.post(
        CreditRequestController.store.url(props.jobOrder.id),
        {
            pricing_entry_id: pricingEntryId.value,
            line_amount: lineAmount.value,
            rush_fee_applied: rushFeeApplied.value,
            discount_type: discountType.value || null,
            discount_value: discountValue.value ?? null,
        },
        {
            preserveScroll: true,
            onError: (errors) => {
                paymentForm.value?.setError(errors);
                creditRequestDialogOpen.value = false;
            },
            onSuccess: () => {
                creditRequestDialogOpen.value = false;
            },
            onFinish: () => {
                creditRequestingProcessing.value = false;
            },
        },
    );
}

const paymentLinkProcessing = ref(false);

const paymentForm = useTemplateRef<FormComponentRef>('paymentForm');

function handleCreditDialogClose(event: Event): void {
    if (!paymentForm.value?.hasErrors) {
        return;
    }

    event.preventDefault();

    void nextTick(() => {
        const pricingCard = document.querySelector<HTMLElement>(
            '[data-test="pricing-card"]',
        );

        pricingCard?.focus({ preventScroll: true });
        pricingCard?.scrollIntoView({ block: 'start', behavior: 'smooth' });
    });
}

/**
 * Save the price (first visit only) and email the customer a link to pay
 * online. Pricing fields are always included; PaymentLinkController only
 * reads them while the job order is still unpriced.
 *
 * This is not the outer <Form>'s own submission, so its validation errors
 * are handed to that form to show beside the pricing fields, which sit a
 * screen above this button on a phone.
 */
function sendPaymentLink(): void {
    paymentLinkProcessing.value = true;
    paymentForm.value?.clearErrors();

    router.post(
        PaymentLinkController.store.url(props.jobOrder.id),
        {
            pricing_entry_id: pricingEntryId.value,
            line_amount: lineAmount.value,
            rush_fee_applied: rushFeeApplied.value,
            discount_type: discountType.value || null,
            discount_value: discountValue.value ?? null,
        },
        {
            preserveScroll: true,
            onError: (errors) => {
                paymentForm.value?.setError(errors);

                void nextTick(() =>
                    document
                        .querySelector('[data-test="pricing-card"]')
                        ?.scrollIntoView({
                            block: 'start',
                            behavior: 'smooth',
                        }),
                );
            },
            onFinish: () => {
                paymentLinkProcessing.value = false;
            },
        },
    );
}

const discountCapHelper = computed(() =>
    discountType.value === 'percentage'
        ? `Up to ${props.discountCapPercentage}%`
        : `Up to ₱${props.discountCapFlatAmount}`,
);
</script>

<template>
    <Head :title="`Job Order Payment — ${jobOrder.description}`" />

    <PageContainer>
        <PageHeader
            :title="`Job Order Payment — ${jobOrder.description}`"
            description="Confirm the pricing, then take the payment. Both panels submit together."
        />

        <Form
            ref="paymentForm"
            v-bind="PaymentController.store.form(jobOrder.id)"
            :options="{ preserveScroll: true }"
            class="grid gap-6 @3xl:grid-cols-2 @3xl:items-start"
            v-slot="{ errors, processing }"
        >
            <Card data-test="pricing-card" tabindex="-1">
                <CardHeader :icon="Receipt">
                    <CardTitle>Pricing</CardTitle>
                </CardHeader>
                <CardContent class="grid gap-4">
                    <template v-if="!pricingLocked">
                        <input
                            type="hidden"
                            name="pricing_entry_id"
                            :value="pricingEntryId ?? ''"
                        />

                        <div class="grid gap-2">
                            <Label for="pricing-entry-select">
                                Product / Service
                            </Label>
                            <SearchableSelect
                                id="pricing-entry-select"
                                :model-value="pricingEntryId?.toString() ?? ''"
                                :options="pricingOptions"
                                placeholder="Search the price list…"
                                empty-text="No service matches that search."
                                @update:model-value="onPricingEntryChange"
                            />
                            <InputError :message="errors.pricing_entry_id" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="line-amount-input"> Line Amount </Label>
                            <Input
                                id="line-amount-input"
                                v-model="lineAmount"
                                name="line_amount"
                                type="number"
                                step="0.01"
                                min="0"
                            />
                            <InputError :message="errors.line_amount" />
                        </div>

                        <div class="flex items-center gap-2">
                            <!--
                                The hidden "0" is load-bearing and must stay
                                ahead of the Switch. reka-ui's SwitchRoot
                                renders a real checkbox, which the browser
                                omits from the submission entirely when
                                unchecked — and `rush_fee_applied` is
                                `required|boolean`, so an unchecked switch
                                sent nothing and the save 422'd. With the
                                hidden field first, an unchecked switch
                                submits "0" and a checked one submits "0"
                                then "1", which PHP resolves to the last
                                value. The explicit value="1" replaces
                                SwitchRoot's default of the string 'on',
                                which fails the `boolean` rule outright.
                            -->
                            <input
                                type="hidden"
                                name="rush_fee_applied"
                                value="0"
                            />
                            <Switch
                                id="rush-fee-switch"
                                v-model="rushFeeApplied"
                                name="rush_fee_applied"
                                value="1"
                            />
                            <Label for="rush-fee-switch">
                                Apply Rush Fee (+{{ rushFeePercentage }}%)
                            </Label>
                            <InputError :message="errors.rush_fee_applied" />
                        </div>

                        <div class="grid gap-2">
                            <Label>Discount</Label>
                            <RadioGroup
                                name="discount_type"
                                :model-value="discountType"
                                @update:model-value="
                                    (value) =>
                                        (discountType =
                                            value as typeof discountType)
                                "
                            >
                                <div class="flex items-center gap-2">
                                    <RadioGroupItem
                                        id="discount-type-none"
                                        value=""
                                    />
                                    <Label for="discount-type-none">
                                        None
                                    </Label>
                                </div>
                                <div class="flex items-center gap-2">
                                    <RadioGroupItem
                                        id="discount-type-percentage"
                                        value="percentage"
                                    />
                                    <Label for="discount-type-percentage">
                                        Percentage
                                    </Label>
                                </div>
                                <div class="flex items-center gap-2">
                                    <RadioGroupItem
                                        id="discount-type-flat"
                                        value="flat"
                                    />
                                    <Label for="discount-type-flat">
                                        Flat Amount
                                    </Label>
                                </div>
                            </RadioGroup>
                            <InputError :message="errors.discount_type" />
                        </div>

                        <div v-if="discountType !== ''" class="grid gap-2">
                            <Label for="discount-value-input">
                                Discount Value
                            </Label>
                            <Input
                                id="discount-value-input"
                                v-model="discountValue"
                                name="discount_value"
                                type="number"
                                step="0.01"
                                min="0"
                            />
                            <p class="text-muted-foreground text-sm">
                                {{ discountCapHelper }}
                            </p>
                            <InputError :message="errors.discount_value" />
                        </div>

                        <PricingSummary :summary="pricingSummary" />
                    </template>

                    <PricingSummary v-else :summary="pricingSummary" />
                </CardContent>
            </Card>

            <Card>
                <CardHeader :icon="CreditCard">
                    <CardTitle>Payment</CardTitle>
                </CardHeader>
                <CardContent class="grid gap-4">
                    <div
                        v-if="pendingPaymongoAmount !== null"
                        role="status"
                        class="border-warning/40 bg-warning/10 grid gap-3 rounded-lg border px-4 py-3 text-sm"
                        data-test="pending-online-payment-notice"
                    >
                        <p class="flex items-start gap-2">
                            <Clock
                                aria-hidden="true"
                                class="text-warning mt-0.5 size-4 shrink-0"
                            />
                            <span>
                                The customer started an online
                                {{ pendingOnlineMethodLabel }} payment of
                                <span class="font-semibold tabular-nums">
                                    {{ money(pendingPaymongoAmount) }}
                                </span>
                                that is not confirmed yet. Check it first. If
                                they did not finish it, cancel it to take the
                                payment here, so they are not charged twice.
                            </span>
                        </p>
                        <div class="flex flex-wrap gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                :disabled="
                                    checkingPaymentStatus ||
                                    cancellingOnlinePayment
                                "
                                data-test="check-payment-status-button"
                                @click="checkPaymentStatus"
                            >
                                <Spinner v-if="checkingPaymentStatus" />
                                Check Payment Status
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                :disabled="
                                    checkingPaymentStatus ||
                                    cancellingOnlinePayment
                                "
                                data-test="cancel-online-payment-button"
                                @click="cancelOnlinePayment"
                            >
                                <Spinner v-if="cancellingOnlinePayment" />
                                Cancel Online Payment
                            </Button>
                        </div>
                    </div>

                    <div
                        class="bg-muted flex items-baseline justify-between gap-4 rounded-lg px-4 py-3"
                    >
                        <span class="text-muted-foreground text-sm">
                            Amount due
                        </span>
                        <span
                            class="text-2xl font-semibold tabular-nums"
                            data-test="amount-due"
                        >
                            {{ money(targetAmount) }}
                        </span>
                    </div>

                    <div class="grid gap-2">
                        <Label>Payment Method</Label>
                        <RadioGroup
                            name="payment_method"
                            :model-value="paymentMethod"
                            @update:model-value="
                                (value) =>
                                    (paymentMethod =
                                        value as typeof paymentMethod)
                            "
                        >
                            <div class="flex items-center gap-2">
                                <RadioGroupItem
                                    id="payment-method-cash"
                                    value="cash"
                                />
                                <Banknote class="size-4" />
                                <Label for="payment-method-cash"> Cash </Label>
                            </div>
                            <div class="flex items-center gap-2">
                                <RadioGroupItem
                                    id="payment-method-bank-transfer"
                                    value="bank_transfer"
                                />
                                <Landmark class="size-4" />
                                <Label for="payment-method-bank-transfer">
                                    Bank Transfer
                                </Label>
                            </div>
                            <div class="flex items-center gap-2">
                                <RadioGroupItem
                                    id="payment-method-gcash"
                                    value="gcash"
                                />
                                <Wallet class="size-4" />
                                <Label for="payment-method-gcash">
                                    GCash
                                </Label>
                            </div>
                            <div class="flex items-center gap-2">
                                <RadioGroupItem
                                    id="payment-method-maya"
                                    value="maya"
                                />
                                <Smartphone class="size-4" />
                                <Label for="payment-method-maya"> Maya </Label>
                            </div>
                            <div class="flex items-center gap-2">
                                <RadioGroupItem
                                    id="payment-method-on-credit"
                                    value="on_credit"
                                />
                                <CreditCard class="size-4" />
                                <Label for="payment-method-on-credit">
                                    On Credit
                                </Label>
                            </div>
                        </RadioGroup>
                        <InputError :message="errors.payment_method" />
                    </div>

                    <div v-if="!isOnCredit" class="grid gap-2">
                        <Label>Payment Type</Label>
                        <RadioGroup
                            name="payment_type"
                            :model-value="paymentType"
                            @update:model-value="
                                (value) =>
                                    (paymentType = value as typeof paymentType)
                            "
                        >
                            <div class="flex items-center gap-2">
                                <RadioGroupItem
                                    id="payment-type-full"
                                    value="full"
                                />
                                <Label for="payment-type-full">
                                    Full Payment
                                </Label>
                            </div>
                            <div class="flex items-center gap-2">
                                <RadioGroupItem
                                    id="payment-type-down"
                                    value="down"
                                />
                                <Label for="payment-type-down">
                                    Down Payment
                                </Label>
                            </div>
                        </RadioGroup>
                        <InputError :message="errors.payment_type" />
                    </div>

                    <div
                        v-if="!isOnCredit && paymentType === 'down'"
                        class="grid gap-2"
                    >
                        <Label for="down-payment-amount-input">
                            Down Payment Amount
                        </Label>
                        <Input
                            id="down-payment-amount-input"
                            v-model="downPaymentAmount"
                            name="down_payment_amount"
                            type="number"
                            step="0.01"
                            min="0.01"
                        />
                        <p class="text-muted-foreground text-sm tabular-nums">
                            Balance left after this:
                            {{ money(downPaymentRemainingBalance) }}
                        </p>
                        <InputError :message="errors.down_payment_amount" />
                        <Button
                            v-if="
                                paymentMethod === 'cash' &&
                                !showDownPaymentChange
                            "
                            type="button"
                            variant="link"
                            class="h-auto w-fit p-0"
                            data-test="show-down-payment-change"
                            @click="showDownPaymentChange = true"
                        >
                            Customer handing over more? Work out the change
                        </Button>
                    </div>

                    <div
                        v-if="
                            paymentMethod === 'cash' &&
                            (paymentType === 'full' || showDownPaymentChange)
                        "
                        class="grid gap-2"
                    >
                        <Label for="cash-received-input">
                            Cash Received
                            <span class="text-muted-foreground font-normal">
                                (optional)
                            </span>
                        </Label>
                        <Input
                            id="cash-received-input"
                            v-model="cashReceived"
                            type="number"
                            step="0.01"
                            min="0"
                        />
                        <p
                            class="text-muted-foreground text-sm tabular-nums"
                            data-test="cash-change"
                        >
                            {{ cashHint }}
                        </p>
                    </div>

                    <div v-if="referenceLabel" class="grid gap-2">
                        <Label for="reference-number-input">
                            {{ referenceLabel }}
                        </Label>
                        <Input
                            id="reference-number-input"
                            v-model="referenceNumber"
                            name="reference_number"
                            type="text"
                            autocomplete="off"
                            aria-describedby="reference-number-hint"
                        />
                        <p
                            id="reference-number-hint"
                            class="text-muted-foreground text-sm"
                        >
                            The reference number on the customer's payment
                            confirmation. Check the amount on it before
                            recording.
                        </p>
                        <InputError :message="errors.reference_number" />
                    </div>

                    <AlertDialog
                        v-if="isOnCredit"
                        v-model:open="creditRequestDialogOpen"
                    >
                        <AlertDialogTrigger as-child>
                            <Button
                                type="button"
                                :disabled="hasPendingOnlinePayment"
                                data-test="record-payment-button"
                            >
                                {{ submitLabel }}
                            </Button>
                        </AlertDialogTrigger>
                        <AlertDialogContent
                            @close-auto-focus="handleCreditDialogClose"
                        >
                            <AlertDialogHeader>
                                <AlertDialogTitle>
                                    Request On-Credit approval for
                                    {{ money(targetAmount) }}?
                                </AlertDialogTitle>
                                <AlertDialogDescription>
                                    This job order will be flagged "Credit
                                    Pending Approval" until an Admin reviews it.
                                    The customer cannot pick up the order until
                                    it's approved or paid another way.
                                </AlertDialogDescription>
                            </AlertDialogHeader>
                            <AlertDialogFooter>
                                <AlertDialogCancel>Cancel</AlertDialogCancel>
                                <Button
                                    type="button"
                                    :disabled="creditRequestingProcessing"
                                    data-test="confirm-credit-request-button"
                                    @click="submitCreditRequest"
                                >
                                    <Spinner
                                        v-if="creditRequestingProcessing"
                                    />
                                    Send for Approval
                                </Button>
                            </AlertDialogFooter>
                        </AlertDialogContent>
                    </AlertDialog>

                    <Button
                        v-else
                        type="submit"
                        :disabled="processing || hasPendingOnlinePayment"
                        data-test="record-payment-button"
                    >
                        {{ submitLabel }}
                    </Button>

                    <!-- Not for an order already on credit: its tracking page
                         offers no online payment, so a link would lead
                         nowhere. -->
                    <div
                        v-if="
                            !isOnCredit &&
                            jobOrder.payment_status !== 'on_credit'
                        "
                        class="border-border grid gap-2 border-t pt-4"
                    >
                        <Button
                            type="button"
                            variant="outline"
                            :disabled="
                                processing ||
                                paymentLinkProcessing ||
                                hasPendingOnlinePayment
                            "
                            data-test="send-payment-link-button"
                            @click="sendPaymentLink"
                        >
                            <Spinner v-if="paymentLinkProcessing" />
                            {{
                                pricingLocked
                                    ? 'Email payment link'
                                    : 'Save price & email payment link'
                            }}
                        </Button>
                        <p class="text-muted-foreground text-sm">
                            For a customer who is not at the counter. They pay
                            the full amount by GCash or Maya from their tracking
                            link.
                        </p>
                    </div>
                </CardContent>
            </Card>
        </Form>
    </PageContainer>
</template>

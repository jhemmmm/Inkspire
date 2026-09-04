<script setup lang="ts">
import { Form, Head, router } from '@inertiajs/vue3';
import { Banknote, Landmark, Smartphone, Wallet } from '@lucide/vue';
import { computed, ref } from 'vue';
import PaymentController from '@/actions/App/Http/Controllers/Cashier/PaymentController';
import InputError from '@/components/InputError.vue';
import PaymentQrCode from '@/components/PaymentQrCode.vue';
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
    rush_fee_amount: number | null;
    discount_amount: number | null;
    pricing_entry: PricingEntryOption | null;
}

const props = defineProps<{
    jobOrder: JobOrderPaymentJobOrder;
    pricingEntries: PricingEntryOption[];
    rushFeePercentage: number;
    discountCapPercentage: number;
    discountCapFlatAmount: number;
    hasExistingTransactions: boolean;
    amountPaid: number;
    remainingBalance: number | null;
    paymongoRedirectUrl: string | null;
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
const lineAmount = ref<number>(Number(props.jobOrder.base_price_snapshot ?? 0));
const rushFeeApplied = ref<boolean>(Boolean(props.jobOrder.rush_fee_amount));
const discountType = ref<'' | 'percentage' | 'flat'>('');
const discountValue = ref<number | undefined>(undefined);

const paymentMethod = ref<'cash' | 'bank_transfer' | 'gcash' | 'maya'>('cash');
const paymentType = ref<'full' | 'down'>('full');
const amountTendered = ref<number | undefined>(undefined);
const referenceNumber = ref('');
const downPaymentAmount = ref<number | undefined>(undefined);

/**
 * Flips to 'qr' once the server flashes back a PayMongo redirect URL after
 * a "Generate QR Code" submission (POS-03) — re-derived on every fresh
 * visit to this page, since a full Inertia redirect remounts the
 * component with new props.
 */
const subView = ref<'form' | 'qr'>(props.paymongoRedirectUrl ? 'qr' : 'form');

const isPaymongoMethod = computed(
    () => paymentMethod.value === 'gcash' || paymentMethod.value === 'maya',
);

/**
 * Sourced from the persisted pending Transaction (via props), not the
 * local paymentMethod ref, since a full Inertia redirect resets local
 * form state back to its default before this label is needed.
 */
const paymongoMethodLabel = computed(() =>
    props.pendingPaymongoMethod === 'gcash' ? 'GCash' : 'Maya',
);

/**
 * Discards the pending PayMongo intent client-side only (D-13) — the
 * intent itself simply expires unused on PayMongo's side, no server call
 * needed to "cancel" it.
 */
function switchPaymentMethod(): void {
    subView.value = 'form';
    paymentMethod.value = 'cash';
}

const checkingPaymentStatus = ref(false);

/**
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

function round2(value: number): number {
    return Math.round(value * 100) / 100;
}

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
 * The amount a "Full Payment" would settle right now — either the live
 * preview total (first pricing/payment visit) or the already-snapshotted
 * job order's remaining balance (a follow-up visit).
 */
const targetAmount = computed<number>(() =>
    props.hasExistingTransactions
        ? (props.remainingBalance ?? 0)
        : breakdown.value.total,
);

const change = computed<number | null>(() => {
    const tendered = Number(amountTendered.value) || 0;

    return tendered > targetAmount.value
        ? round2(tendered - targetAmount.value)
        : null;
});

const downPaymentRemainingBalance = computed<number>(() =>
    round2(targetAmount.value - (Number(downPaymentAmount.value) || 0)),
);

const submitLabel = computed(() => {
    if (paymentType.value === 'down') {
        return 'Record Down Payment';
    }

    return isPaymongoMethod.value ? 'Generate QR Code' : 'Record Payment';
});

const discountCapHelper = computed(() =>
    discountType.value === 'percentage'
        ? `Up to ${props.discountCapPercentage}%`
        : `Up to ₱${props.discountCapFlatAmount}`,
);
</script>

<template>
    <Head :title="`Job Order Payment — ${jobOrder.description}`" />

    <div
        class="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4"
    >
        <h1 class="text-[28px] leading-[1.2] font-semibold">
            Job Order Payment — {{ jobOrder.description }}
        </h1>

        <Form
            v-bind="PaymentController.store.form(jobOrder.id)"
            :options="{ preserveScroll: true }"
            class="flex flex-col gap-6"
            v-slot="{ errors, processing }"
        >
            <Card>
                <CardHeader>
                    <CardTitle>Pricing</CardTitle>
                </CardHeader>
                <CardContent class="grid gap-4">
                    <template v-if="!hasExistingTransactions">
                        <input
                            type="hidden"
                            name="pricing_entry_id"
                            :value="pricingEntryId ?? ''"
                        />

                        <div class="grid gap-2">
                            <Label for="pricing-entry-select">
                                Product / Service
                            </Label>
                            <Select
                                :model-value="pricingEntryId?.toString()"
                                @update:model-value="onPricingEntryChange"
                            >
                                <SelectTrigger id="pricing-entry-select">
                                    <SelectValue
                                        placeholder="Select a product or service"
                                    />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="entry in pricingEntries"
                                        :key="entry.id"
                                        :value="entry.id.toString()"
                                    >
                                        {{ entry.name }} — ₱{{
                                            entry.base_price
                                        }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
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
                            <Switch
                                id="rush-fee-switch"
                                v-model="rushFeeApplied"
                                name="rush_fee_applied"
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

                        <div class="flex flex-col gap-1 pt-2">
                            <div class="flex items-center justify-between">
                                <span>Base Price</span>
                                <span>₱{{ breakdown.base.toFixed(2) }}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span>Rush Fee</span>
                                <span>
                                    ₱{{ breakdown.rushFeeAmount.toFixed(2) }}
                                </span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span>Discount</span>
                                <span>
                                    ₱{{ breakdown.discountAmount.toFixed(2) }}
                                </span>
                            </div>
                            <div
                                class="flex items-center justify-between pt-2 text-[28px] leading-[1.2] font-semibold"
                            >
                                <span>Total</span>
                                <span>₱{{ breakdown.total.toFixed(2) }}</span>
                            </div>
                        </div>
                    </template>

                    <div v-else class="flex flex-col gap-1">
                        <div class="flex items-center justify-between">
                            <span>Base Price</span>
                            <span>
                                ₱{{
                                    Number(
                                        jobOrder.base_price_snapshot ?? 0,
                                    ).toFixed(2)
                                }}
                            </span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span>Rush Fee</span>
                            <span>
                                ₱{{
                                    Number(
                                        jobOrder.rush_fee_amount ?? 0,
                                    ).toFixed(2)
                                }}
                            </span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span>Discount</span>
                            <span>
                                ₱{{
                                    Number(
                                        jobOrder.discount_amount ?? 0,
                                    ).toFixed(2)
                                }}
                            </span>
                        </div>
                        <div
                            class="flex items-center justify-between pt-2 text-[28px] leading-[1.2] font-semibold"
                        >
                            <span>Total</span>
                            <span>
                                ₱{{
                                    Number(jobOrder.total_amount ?? 0).toFixed(
                                        2,
                                    )
                                }}
                            </span>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Payment</CardTitle>
                </CardHeader>
                <CardContent v-if="subView === 'qr'" class="grid gap-4">
                    <h2 class="text-lg font-semibold">Scan to Pay</h2>

                    <PaymentQrCode
                        v-if="paymongoRedirectUrl"
                        :redirect-url="paymongoRedirectUrl"
                    />

                    <p class="text-muted-foreground text-sm">
                        Ask the customer to scan this code with their
                        {{ paymongoMethodLabel }} app to complete the ₱{{
                            (pendingPaymongoAmount ?? 0).toFixed(2)
                        }}
                        payment.
                    </p>

                    <Button
                        type="button"
                        :disabled="checkingPaymentStatus"
                        data-test="check-payment-status-button"
                        @click="checkPaymentStatus"
                    >
                        <Spinner v-if="checkingPaymentStatus" />
                        Check Payment Status
                    </Button>

                    <Button
                        type="button"
                        variant="outline"
                        data-test="switch-payment-method-button"
                        @click="switchPaymentMethod"
                    >
                        Switch Payment Method
                    </Button>
                </CardContent>

                <CardContent v-else class="grid gap-4">
                    <p
                        v-if="hasExistingTransactions"
                        class="text-muted-foreground text-sm"
                    >
                        Remaining Balance: ₱{{
                            (remainingBalance ?? 0).toFixed(2)
                        }}
                    </p>

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
                        </RadioGroup>
                        <InputError :message="errors.payment_method" />
                    </div>

                    <div class="grid gap-2">
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

                    <template v-if="paymentMethod === 'cash'">
                        <div class="grid gap-2">
                            <Label for="amount-tendered-input">
                                Amount Tendered
                            </Label>
                            <Input
                                id="amount-tendered-input"
                                v-model="amountTendered"
                                name="amount_tendered"
                                type="number"
                                step="0.01"
                                min="0"
                            />
                            <p
                                v-if="change !== null"
                                class="text-muted-foreground text-sm"
                            >
                                Change: ₱{{ change.toFixed(2) }}
                            </p>
                            <InputError :message="errors.amount_tendered" />
                        </div>
                    </template>

                    <template v-else-if="paymentMethod === 'bank_transfer'">
                        <div class="grid gap-2">
                            <Label for="reference-number-input">
                                Bank Reference Number
                            </Label>
                            <Input
                                id="reference-number-input"
                                v-model="referenceNumber"
                                name="reference_number"
                                type="text"
                            />
                            <InputError :message="errors.reference_number" />
                        </div>
                    </template>

                    <div v-if="paymentType === 'down'" class="grid gap-2">
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
                        <InputError :message="errors.down_payment_amount" />
                    </div>

                    <Button
                        type="submit"
                        :disabled="processing"
                        data-test="record-payment-button"
                    >
                        {{ submitLabel }}
                    </Button>

                    <p
                        v-if="paymentType === 'down'"
                        class="text-muted-foreground text-sm"
                    >
                        Remaining Balance: ₱{{
                            downPaymentRemainingBalance.toFixed(2)
                        }}
                    </p>
                </CardContent>
            </Card>
        </Form>
    </div>
</template>

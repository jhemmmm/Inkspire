<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { MailCheck, Plus, Send } from '@lucide/vue';
import { computed, nextTick } from 'vue';
import OnlineOrderController from '@/actions/App/Http/Controllers/Public/OnlineOrderController';
import InputError from '@/components/InputError.vue';
import JobOrderRowFields, {
    emptyJobOrderRow,
    type JobOrderRow,
} from '@/components/JobOrderRowFields.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { home } from '@/routes';
import { create } from '@/routes/public/orders';
import { show as trackingShow } from '@/routes/public/tracking';

interface PricingEntry {
    id: number;
    name: string;
    unit: string | null;
}

defineProps<{
    pricingEntries: PricingEntry[];
    specificationOptions: Record<string, string[]>;
    printSizeDimensions: Record<
        string,
        { width_inches: number | null; height_inches: number | null }
    >;
    acceptedFileFormats: string[];
    sentTo?: string | null;
}>();

const MAX_ITEMS = 5;

const form = useForm({
    name: '',
    organization: '',
    contact_number: '',
    email: '',
    address: '',
    website: '',
    job_orders: [emptyJobOrderRow()] as JobOrderRow[],
});

function addRow(): void {
    if (form.job_orders.length < MAX_ITEMS) {
        form.job_orders.push(emptyJobOrderRow());
    }
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

/** Every distinct problem in one list, so none hides below the fold. */
const errorSummary = computed(() => [
    ...new Set(Object.values(form.errors).filter(Boolean)),
]);

function submit(): void {
    form.transform((data) => ({
        ...data,
        // The shop sets the price; the server ignores one sent from here.
        job_orders: data.job_orders.map(
            ({
                quoted_amount: _quoted,
                quoted_amount_overridden: _overridden,
                _key,
                ...row
            }) => row,
        ),
    })).post(OnlineOrderController.store().url, {
        forceFormData: true,
        preserveScroll: true,
        onError: () =>
            void nextTick(() =>
                document
                    .getElementById('order-error-summary')
                    ?.scrollIntoView({ block: 'center', behavior: 'smooth' }),
            ),
    });
}
</script>

<template>
    <Head title="Order online" />

    <div
        class="bg-muted text-foreground flex min-h-screen flex-col overflow-x-clip"
    >
        <header
            class="mx-auto flex w-full max-w-3xl items-center justify-between gap-4 px-4 py-4 sm:px-6 sm:py-5"
        >
            <Link :href="home()" class="rounded-md">
                <img
                    src="/logo.png"
                    alt="Inkspire home"
                    width="824"
                    height="303"
                    class="h-8 w-auto sm:h-9"
                />
            </Link>
            <Link
                :href="trackingShow()"
                class="text-primary text-sm font-semibold"
            >
                Track an order
            </Link>
        </header>

        <main
            class="mx-auto flex w-full max-w-3xl flex-1 flex-col gap-6 px-4 pb-10 sm:px-6"
        >
            <div class="grid gap-1">
                <h1 class="text-2xl font-extrabold tracking-tight sm:text-3xl">
                    Order online
                </h1>
                <p class="text-muted-foreground text-sm">
                    Tell us what you need printed. We email you a link to
                    confirm, then your order goes to the shop.
                </p>
            </div>

            <Card v-if="sentTo" data-test="order-sent-card">
                <CardHeader :icon="MailCheck">
                    <CardTitle>Check your email</CardTitle>
                    <CardDescription>
                        We sent a confirmation link to
                        <span class="text-foreground font-semibold">
                            {{ sentTo }}
                        </span>
                        . Your order is not placed until you open it. The link
                        works for 48 hours.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <Button as-child variant="secondary">
                        <Link :href="create()">Place another order</Link>
                    </Button>
                </CardContent>
            </Card>

            <template v-else>
                <Card>
                    <CardHeader>
                        <CardTitle>Your details</CardTitle>
                        <CardDescription>
                            We use these to send your confirmation and to reach
                            you about your order.
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="grid gap-4 @2xl:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="order-name">Name</Label>
                            <Input
                                id="order-name"
                                v-model="form.name"
                                autocomplete="name"
                            />
                            <InputError :message="form.errors.name" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="order-organization">
                                Organization (optional)
                            </Label>
                            <Input
                                id="order-organization"
                                v-model="form.organization"
                                autocomplete="organization"
                            />
                            <InputError :message="form.errors.organization" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="order-contact-number">
                                Mobile number
                            </Label>
                            <Input
                                id="order-contact-number"
                                v-model="form.contact_number"
                                type="tel"
                                inputmode="tel"
                                autocomplete="tel"
                            />
                            <InputError :message="form.errors.contact_number" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="order-email">Email</Label>
                            <Input
                                id="order-email"
                                v-model="form.email"
                                type="email"
                                inputmode="email"
                                autocomplete="email"
                            />
                            <InputError :message="form.errors.email" />
                        </div>
                        <div class="grid gap-2 @2xl:col-span-2">
                            <Label for="order-address">Address</Label>
                            <Textarea
                                id="order-address"
                                v-model="form.address"
                                rows="2"
                                autocomplete="street-address"
                            />
                            <InputError :message="form.errors.address" />
                        </div>
                        <!-- Honeypot: people never see it, form-stuffing bots fill it. -->
                        <div
                            class="absolute -left-[9999px] h-px w-px overflow-hidden"
                            aria-hidden="true"
                        >
                            <label for="order-website">Website</label>
                            <input
                                id="order-website"
                                v-model="form.website"
                                type="text"
                                name="website"
                                tabindex="-1"
                                autocomplete="off"
                            />
                        </div>
                    </CardContent>
                </Card>

                <div class="@container flex flex-col gap-6">
                    <JobOrderRowFields
                        v-for="(row, index) in form.job_orders"
                        :key="row._key"
                        audience="customer"
                        :row="row"
                        :index="index"
                        :errors="jobOrderRowErrors(index)"
                        :pricing-entries="pricingEntries"
                        :specification-options="specificationOptions"
                        :print-size-dimensions="printSizeDimensions"
                        :rush-fee-percentage="0"
                        :accepted-file-formats="acceptedFileFormats"
                        :removable="form.job_orders.length > 1"
                        @remove="removeRow(index)"
                    />
                </div>

                <div
                    class="bg-background/95 border-border sticky bottom-0 -mx-4 flex flex-col gap-3 border-t px-4 py-4 backdrop-blur sm:-mx-6 sm:px-6"
                >
                    <div
                        v-if="errorSummary.length > 0"
                        id="order-error-summary"
                        role="alert"
                        class="border-destructive/40 bg-destructive/10 text-destructive rounded-lg border px-4 py-3 text-sm"
                        data-test="order-error-summary"
                    >
                        <p class="font-semibold">
                            Your order was not sent. Please fix these:
                        </p>
                        <ul class="mt-1 list-disc pl-5">
                            <li v-for="message in errorSummary" :key="message">
                                {{ message }}
                            </li>
                        </ul>
                    </div>
                    <div
                        class="flex flex-wrap items-center justify-between gap-3"
                    >
                        <Button
                            v-if="form.job_orders.length < MAX_ITEMS"
                            type="button"
                            variant="secondary"
                            data-test="add-item-button"
                            @click="addRow"
                        >
                            <Plus class="size-4" />
                            Add another item
                        </Button>
                        <span v-else class="text-muted-foreground text-sm">
                            You can order up to {{ MAX_ITEMS }} items at a time.
                        </span>
                        <Button
                            type="button"
                            size="lg"
                            :disabled="form.processing"
                            data-test="send-order-button"
                            @click="submit"
                        >
                            <Send class="size-4" />
                            {{ form.processing ? 'Sending…' : 'Send my order' }}
                        </Button>
                    </div>
                </div>
            </template>
        </main>
    </div>
</template>

<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    AlertCircle,
    ArrowRight,
    CircleCheck,
    Clock,
    PencilRuler,
    ScanLine,
} from '@lucide/vue';
import { computed, nextTick, ref } from 'vue';
import InkCard from '@/components/InkCard.vue';
import OrderProgress from '@/components/OrderProgress.vue';
import PublicNotice from '@/components/PublicNotice.vue';
import PublicPage from '@/components/PublicPage.vue';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button, buttonVariants } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { useTrackingPoll } from '@/composables/useLivePoll';
import { money } from '@/lib/jobOrders';
import { create as orderCreate } from '@/routes/public/orders';
import { show as trackingShow } from '@/routes/public/tracking';

interface TrackingTokenResult {
    found: boolean;
    number?: string;
    stage?: string;
    stageStep?: number | null;
    reviewUrl?: string | null;
    payment?: {
        amountDue: number;
        state: 'due' | 'pending' | 'paid';
    } | null;
}

const props = defineProps<{
    result: TrackingTokenResult;
}>();

const isLive = useTrackingPoll(() => props.result);

const page = usePage();

const paying = ref(false);

const paymentError = computed(
    () => (page.props.errors as Record<string, string> | undefined)?.payment,
);

/**
 * Start (or carry on with) a GCash or Maya checkout for the full balance.
 * While a checkout is open, choosing the other wallet switches to it.
 *
 * The pay URL is built from the address this page was opened at, so the
 * tracking token is never put in a prop. The server replies with a redirect
 * to PayMongo's own page, which Inertia follows as a full-page visit.
 */
function pay(method: 'gcash' | 'maya'): void {
    const path = page.url.split('?')[0];

    router.post(
        `${path}/pay`,
        { payment_method: method },
        {
            only: ['result', 'errors'],
            onStart: () => {
                paying.value = true;
            },
            onFinish: () => {
                paying.value = false;
            },
            // The alert renders under the buttons, which on a short screen is
            // below the fold.
            onError: () =>
                void nextTick(() =>
                    document
                        .querySelector(
                            '[data-test="tracking-token-payment-error"]',
                        )
                        ?.scrollIntoView({
                            block: 'center',
                            behavior: 'smooth',
                        }),
                ),
        },
    );
}
</script>

<template>
    <Head title="Your Order" />

    <PublicPage width="max-w-xl">
        <template #aside>
            <Link
                :href="orderCreate()"
                class="text-primary text-sm font-semibold"
            >
                Order online
            </Link>
        </template>

        <InkCard>
            <template v-if="result.found">
                <div class="px-6 pt-9 sm:px-10 sm:pt-11">
                    <div class="bg-primary mb-4 h-[3px] w-10 rounded-full" />
                    <p
                        class="text-muted-foreground text-xs font-semibold tabular-nums"
                        data-test="tracking-token-number"
                    >
                        Job Order {{ result.number }}
                    </p>
                    <h1
                        class="mt-1 text-2xl leading-tight font-extrabold tracking-tight sm:text-3xl"
                        data-test="tracking-token-stage"
                    >
                        {{ result.stage }}
                    </h1>
                    <p
                        class="text-muted-foreground mt-2 text-sm leading-relaxed"
                    >
                        Here is where your order is right now. Keep your slip
                        and scan it again any time.
                    </p>
                </div>

                <!-- What the customer has to do comes before the ladder: on a
                     phone the ladder alone fills most of the first screen. -->
                <div
                    v-if="result.reviewUrl || result.payment"
                    class="flex flex-col gap-4 px-6 pt-6 sm:px-10"
                >
                    <div
                        v-if="result.reviewUrl"
                        class="bg-muted/60 border-border flex flex-col gap-4 rounded-xl border p-5"
                    >
                        <div class="flex items-start gap-3">
                            <span
                                class="bg-accent text-accent-foreground flex size-10 shrink-0 items-center justify-center rounded-xl"
                            >
                                <PencilRuler class="size-5" />
                            </span>
                            <div class="flex flex-col gap-0.5">
                                <p class="font-bold">
                                    Your design is ready for you to check
                                </p>
                                <p class="text-muted-foreground text-sm">
                                    Nothing is printed until you approve it.
                                    Open the design to approve it or ask for
                                    changes.
                                </p>
                            </div>
                        </div>
                        <!--
                            A plain anchor, not an Inertia <Link>: the target is
                            a signed, full-page URL outside this page's own
                            route, and a client-side visit would strip the
                            document reload the signature check expects.
                        -->
                        <a
                            :href="result.reviewUrl"
                            :class="[buttonVariants({ size: 'lg' }), 'w-full']"
                            data-test="tracking-token-review-link"
                        >
                            Review your design
                            <ArrowRight class="size-4" />
                        </a>
                    </div>

                    <div
                        v-if="result.payment"
                        :class="[
                            result.payment.state === 'paid'
                                ? 'bg-success/10 border-success/20'
                                : 'bg-muted/60 border-border',
                            'flex flex-col gap-4 rounded-xl border p-5',
                        ]"
                        data-test="tracking-token-payment"
                    >
                        <template v-if="result.payment.state === 'due'">
                            <div class="flex flex-col gap-1">
                                <p
                                    class="text-muted-foreground text-[11px] font-bold tracking-widest uppercase"
                                >
                                    Amount due
                                </p>
                                <p
                                    class="text-3xl leading-none font-extrabold tracking-tight tabular-nums"
                                    data-test="tracking-token-amount-due"
                                >
                                    {{ money(result.payment.amountDue) }}
                                </p>
                                <p class="text-muted-foreground mt-1 text-sm">
                                    Pay the full amount now, or pay at the shop.
                                </p>
                            </div>
                        </template>

                        <template
                            v-else-if="result.payment.state === 'pending'"
                        >
                            <div class="flex items-start gap-3">
                                <span
                                    class="bg-warning/15 text-warning flex size-10 shrink-0 items-center justify-center rounded-xl"
                                >
                                    <Clock class="size-5" />
                                </span>
                                <div class="flex flex-col gap-0.5">
                                    <p class="font-bold">
                                        We are checking your payment
                                    </p>
                                    <p class="text-muted-foreground text-sm">
                                        This page turns to Paid on its own once
                                        your payment goes through. If you have
                                        not finished paying, carry on below. You
                                        can also choose the other wallet.
                                    </p>
                                </div>
                            </div>
                        </template>

                        <div
                            v-else
                            class="flex items-center gap-3"
                            data-test="tracking-token-paid"
                        >
                            <span
                                class="bg-success/15 text-success flex size-10 shrink-0 items-center justify-center rounded-xl"
                            >
                                <CircleCheck class="size-5" />
                            </span>
                            <p class="font-bold">Paid. Thank you.</p>
                        </div>

                        <!-- Both wallets stay on offer while a checkout is
                             open, so a wrong or failing wallet is never a
                             dead end. -->
                        <div
                            v-if="result.payment.state !== 'paid'"
                            class="grid gap-3 sm:grid-cols-2"
                        >
                            <Button
                                type="button"
                                size="lg"
                                class="w-full"
                                :disabled="paying"
                                data-test="pay-gcash-button"
                                @click="pay('gcash')"
                            >
                                <Spinner v-if="paying" />
                                Pay with GCash
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                size="lg"
                                class="w-full"
                                :disabled="paying"
                                data-test="pay-maya-button"
                                @click="pay('maya')"
                            >
                                <Spinner v-if="paying" />
                                Pay with Maya
                            </Button>
                        </div>

                        <Alert
                            v-if="paymentError"
                            variant="destructive"
                            role="alert"
                            data-test="tracking-token-payment-error"
                        >
                            <AlertCircle class="size-4" />
                            <AlertDescription>
                                {{ paymentError }}
                            </AlertDescription>
                        </Alert>
                    </div>
                </div>

                <div class="px-6 py-7 sm:px-10">
                    <OrderProgress :step="result.stageStep ?? null" />
                </div>

                <p
                    v-if="isLive"
                    class="border-border text-muted-foreground flex items-center gap-2.5 border-t px-6 py-4 text-sm sm:px-10"
                >
                    <span class="relative flex size-2 shrink-0">
                        <span
                            class="bg-success absolute inline-flex size-full animate-ping rounded-full opacity-60 motion-reduce:hidden"
                        />
                        <span class="bg-success relative size-2 rounded-full" />
                    </span>
                    This page updates on its own. Leave it open and it will keep
                    up.
                </p>
            </template>

            <PublicNotice
                v-else
                :icon="ScanLine"
                tone="bg-destructive/10 text-destructive"
                title="We couldn't read that code"
                data-test="tracking-token-missing"
            >
                The code on your slip may be smudged or only partly scanned. Try
                scanning it again, or type the job order number printed under
                it. Staff at the shop counter can also look the order up for
                you.
                <template #actions>
                    <Button as-child size="lg">
                        <Link :href="trackingShow()">
                            Type the number instead
                            <ArrowRight class="size-4" />
                        </Link>
                    </Button>
                </template>
            </PublicNotice>
        </InkCard>
    </PublicPage>
</template>

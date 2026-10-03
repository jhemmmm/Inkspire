<script setup lang="ts">
import { Head, router, usePage, usePoll } from '@inertiajs/vue3';
import { AlertCircle, PencilRuler } from '@lucide/vue';
import { computed, nextTick, ref, watch } from 'vue';
import OrderProgress from '@/components/OrderProgress.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button, buttonVariants } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Spinner } from '@/components/ui/spinner';
import { money } from '@/lib/jobOrders';

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

// Mirrors public/Tracking.vue: 5000ms polling, but only while a found result
// is on screen.
const { start, stop } = usePoll(
    5000,
    { only: ['result'] },
    { autoStart: false },
);

const page = usePage();

const paying = ref(false);

const paymentError = computed(
    () => (page.props.errors as Record<string, string> | undefined)?.payment,
);

/**
 * Start (or carry on with) a GCash or Maya checkout for the full balance.
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

// Stages a job order can never leave. Polling past one of them burns the
// per-IP rate-limit bucket forever on every device that ever scanned the
// slip, without the answer ever changing again.
const TERMINAL_STAGES = ['Completed', 'Cancelled'];

watch(
    () => props.result,
    (value) => {
        if (value?.found && !TERMINAL_STAGES.includes(value.stage ?? '')) {
            start();
        } else {
            stop();
        }
    },
    { immediate: true },
);
</script>

<template>
    <Head title="Your Order" />

    <div
        class="dark bg-background text-foreground flex min-h-screen items-center justify-center p-8"
    >
        <Card class="w-full max-w-md">
            <CardContent class="flex flex-col gap-6">
                <template v-if="result.found">
                    <div class="flex flex-col items-center gap-2 text-center">
                        <h1
                            class="text-primary text-2xl leading-tight font-extrabold tracking-tight"
                        >
                            Your Order
                        </h1>
                        <p class="text-muted-foreground max-w-prose text-sm">
                            Here is where your order is right now. Nothing to
                            type — keep the slip and scan it again any time.
                        </p>
                    </div>

                    <div class="flex flex-col items-center gap-3 text-center">
                        <p
                            class="text-muted-foreground text-sm font-semibold tabular-nums"
                            data-test="tracking-token-number"
                        >
                            Job Order {{ result.number }}
                        </p>
                        <Badge
                            variant="secondary"
                            class="text-base"
                            data-test="tracking-token-stage"
                        >
                            {{ result.stage }}
                        </Badge>
                    </div>

                    <OrderProgress :step="result.stageStep ?? null" />

                    <p class="text-muted-foreground text-center text-sm">
                        This page updates on its own — leave it open and it will
                        keep up.
                    </p>

                    <div
                        v-if="result.payment"
                        class="border-border flex flex-col gap-3 border-t pt-6"
                        data-test="tracking-token-payment"
                    >
                        <template v-if="result.payment.state === 'due'">
                            <div class="flex flex-col gap-1 text-center">
                                <p class="text-muted-foreground text-sm">
                                    Amount due
                                </p>
                                <p
                                    class="text-2xl font-extrabold tabular-nums"
                                    data-test="tracking-token-amount-due"
                                >
                                    {{ money(result.payment.amountDue) }}
                                </p>
                                <p class="text-muted-foreground text-sm">
                                    Pay the full amount now, or pay at the shop.
                                </p>
                            </div>
                            <div class="grid gap-2 sm:grid-cols-2">
                                <Button
                                    type="button"
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
                                    :disabled="paying"
                                    data-test="pay-maya-button"
                                    @click="pay('maya')"
                                >
                                    <Spinner v-if="paying" />
                                    Pay with Maya
                                </Button>
                            </div>
                        </template>

                        <template
                            v-else-if="result.payment.state === 'pending'"
                        >
                            <p class="text-center font-semibold">
                                We are waiting for your payment to be confirmed
                            </p>
                            <p
                                class="text-muted-foreground text-center text-sm"
                            >
                                If you have not finished paying, you can pick up
                                where you left off.
                            </p>
                            <Button
                                type="button"
                                variant="outline"
                                :disabled="paying"
                                data-test="pay-continue-button"
                                @click="pay('gcash')"
                            >
                                <Spinner v-if="paying" />
                                Check or continue payment
                            </Button>
                        </template>

                        <p
                            v-else
                            class="text-center font-semibold"
                            data-test="tracking-token-paid"
                        >
                            Paid. Thank you.
                        </p>

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

                    <div
                        v-if="result.reviewUrl"
                        class="border-border flex flex-col gap-3 border-t pt-6"
                    >
                        <div class="flex flex-col gap-1 text-center">
                            <p class="font-semibold">
                                Your design is ready for you to check
                            </p>
                            <p class="text-muted-foreground text-sm">
                                Nothing is printed until you approve it. Open
                                the design to approve it or ask for changes.
                            </p>
                        </div>
                        <!--
                            A plain anchor, not an Inertia <Link>: the target is
                            a signed, full-page URL outside this page's own
                            route, and a client-side visit would strip the
                            document reload the signature check expects.
                        -->
                        <a
                            :href="result.reviewUrl"
                            :class="buttonVariants()"
                            data-test="tracking-token-review-link"
                        >
                            <PencilRuler class="size-4" />
                            Review your design
                        </a>
                    </div>
                </template>

                <template v-else>
                    <div class="flex flex-col items-center gap-2 text-center">
                        <h1
                            class="text-primary text-2xl leading-tight font-extrabold tracking-tight"
                        >
                            Your Order
                        </h1>
                        <p class="text-muted-foreground max-w-prose text-sm">
                            This page shows the live status of an order from a
                            printed slip.
                        </p>
                    </div>
                    <Alert
                        variant="destructive"
                        data-test="tracking-token-missing"
                    >
                        <AlertCircle class="size-4" />
                        <AlertTitle>We couldn't read that code</AlertTitle>
                        <AlertDescription>
                            The code on your slip may be smudged or only partly
                            scanned. Try scanning it again, or show the slip at
                            the shop counter and staff can look the order up for
                            you.
                        </AlertDescription>
                    </Alert>
                </template>
            </CardContent>
        </Card>
    </div>
</template>

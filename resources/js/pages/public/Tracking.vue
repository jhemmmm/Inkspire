<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { AlertCircle, ArrowRight, QrCode } from '@lucide/vue';
import { ref } from 'vue';
import TrackingController from '@/actions/App/Http/Controllers/Public/TrackingController';
import InkCard from '@/components/InkCard.vue';
import InputError from '@/components/InputError.vue';
import OrderProgress from '@/components/OrderProgress.vue';
import PublicPage from '@/components/PublicPage.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useTrackingPoll } from '@/composables/useLivePoll';
import { normalizeJobOrderNumber } from '@/lib/jobOrders';
import { create as orderCreate } from '@/routes/public/orders';

interface TrackingResult {
    found: boolean;
    number?: string;
    stage?: string;
    stageStep?: number | null;
}

const props = defineProps<{
    result: TrackingResult | null;
}>();

const page = usePage();

const numberInput = ref('');

/** Follows JobOrder's JO-{year}-{0000} format, so the example never looks stale. */
const placeholderNumber = `JO-${new Date().getFullYear()}-0001`;
const processing = ref(false);

// D-14: polls only while a found result is on screen — the bare lookup form
// must never poll.
const isLive = useTrackingPoll(() => props.result);

function lookup(): void {
    router.get(
        TrackingController.show.url(),
        { number: normalizeJobOrderNumber(numberInput.value) },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            onStart: () => (processing.value = true),
            onFinish: () => (processing.value = false),
        },
    );
}

function checkAnother(): void {
    numberInput.value = '';
    router.get(TrackingController.show.url(), {}, { replace: true });
}
</script>

<template>
    <Head title="Track Your Order" />

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
            <template v-if="result?.found === true">
                <div class="px-6 pt-9 sm:px-10 sm:pt-11">
                    <div class="bg-primary mb-4 h-[3px] w-10 rounded-full" />
                    <p
                        class="text-muted-foreground text-xs font-semibold tabular-nums"
                    >
                        Job Order {{ result.number }}
                    </p>
                    <h1
                        class="mt-1 text-2xl leading-tight font-extrabold tracking-tight sm:text-3xl"
                    >
                        {{ result.stage }}
                    </h1>
                    <p
                        class="text-muted-foreground mt-2 text-sm leading-relaxed"
                    >
                        Here is where your order is right now. To pay online or
                        to check your design, scan the QR code on your slip.
                    </p>
                </div>

                <div class="px-6 py-7 sm:px-10">
                    <OrderProgress :step="result.stageStep ?? null" />
                </div>

                <div
                    class="border-border flex flex-col gap-4 border-t px-6 py-5 sm:px-10"
                >
                    <p
                        v-if="isLive"
                        class="text-muted-foreground flex items-center gap-2.5 text-sm"
                    >
                        <span class="relative flex size-2 shrink-0">
                            <span
                                class="bg-success absolute inline-flex size-full animate-ping rounded-full opacity-60 motion-reduce:hidden"
                            />
                            <span
                                class="bg-success relative size-2 rounded-full"
                            />
                        </span>
                        This page updates on its own. Leave it open and it will
                        keep up.
                    </p>
                    <Button
                        variant="outline"
                        size="lg"
                        class="w-full"
                        data-test="check-another-order-button"
                        @click="checkAnother"
                    >
                        Check another order
                    </Button>
                </div>
            </template>

            <div v-else class="flex flex-col gap-6 px-6 py-9 sm:px-10 sm:py-11">
                <div>
                    <div class="bg-primary mb-4 h-[3px] w-10 rounded-full" />
                    <h1
                        class="text-2xl leading-tight font-extrabold tracking-tight sm:text-3xl"
                    >
                        Track your order
                    </h1>
                    <p
                        class="text-muted-foreground mt-2 text-sm leading-relaxed"
                    >
                        Type the job order number from your slip or receipt. You
                        don’t need an account.
                    </p>
                </div>

                <Alert v-if="result?.found === false" variant="destructive">
                    <AlertCircle class="size-4" />
                    <AlertTitle>We couldn't find that order</AlertTitle>
                    <AlertDescription>
                        Check the job order number on your receipt and try
                        again.
                    </AlertDescription>
                </Alert>

                <form class="flex flex-col gap-4" @submit.prevent="lookup">
                    <div class="grid gap-2">
                        <Label for="tracking-number">Job order number</Label>
                        <Input
                            id="tracking-number"
                            v-model="numberInput"
                            :placeholder="placeholderNumber"
                            required
                            autocomplete="off"
                            autocapitalize="characters"
                            spellcheck="false"
                            enterkeyhint="go"
                            class="h-12 text-lg font-bold tracking-wide uppercase tabular-nums placeholder:font-semibold md:text-lg"
                        />
                        <InputError :message="page.props.errors?.number" />
                    </div>
                    <Button
                        type="submit"
                        size="lg"
                        class="w-full"
                        :disabled="processing"
                        data-test="check-status-button"
                    >
                        <Spinner v-if="processing" />
                        Check status
                        <ArrowRight v-if="!processing" class="size-4" />
                    </Button>
                </form>

                <p
                    class="text-muted-foreground flex items-start gap-2.5 text-[13px] leading-snug"
                >
                    <QrCode
                        aria-hidden="true"
                        class="mt-0.5 size-5 shrink-0 stroke-[1.75]"
                    />
                    The number is printed under the QR code on your slip. Or
                    scan the code to open your order directly.
                </p>
            </div>
        </InkCard>
    </PublicPage>
</template>

<script setup lang="ts">
import { Head, usePoll } from '@inertiajs/vue3';
import { AlertCircle, PencilRuler } from '@lucide/vue';
import { watch } from 'vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { buttonVariants } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';

interface TrackingTokenResult {
    found: boolean;
    number?: string;
    stage?: string;
    reviewUrl?: string | null;
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
                            This is the live status of the order on your slip.
                            Nothing to type — keep the slip and scan it again
                            any time.
                        </p>
                    </div>

                    <div class="flex flex-col items-center gap-3 text-center">
                        <p
                            class="text-muted-foreground text-lg font-semibold tabular-nums"
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
                        <p class="text-muted-foreground text-sm">
                            This page updates automatically.
                        </p>
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

<script setup lang="ts">
import { Head, router, usePage, usePoll } from '@inertiajs/vue3';
import { AlertCircle } from '@lucide/vue';
import { ref, watch } from 'vue';
import TrackingController from '@/actions/App/Http/Controllers/Public/TrackingController';
import InputError from '@/components/InputError.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

interface TrackingResult {
    found: boolean;
    number?: string;
    stage?: string;
}

const props = defineProps<{
    result: TrackingResult | null;
}>();

const page = usePage();

const numberInput = ref('');
const processing = ref(false);

// D-14: 5000ms polling, matching QueueDisplay.vue, but only while a found
// result is on screen — the bare lookup form must never poll.
const { start, stop } = usePoll(
    5000,
    { only: ['result'] },
    { autoStart: false },
);

// Stages a job order can never leave. Polling past one of them burns the
// per-IP rate-limit bucket forever on every device that ever looked the
// order up, without the answer ever changing again.
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

function lookup(): void {
    router.get(
        TrackingController.show.url(),
        { number: numberInput.value.trim() },
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

    <div
        class="dark bg-background text-foreground flex min-h-screen items-center justify-center p-8"
    >
        <Card class="w-full max-w-md">
            <CardContent class="flex flex-col gap-6">
                <template v-if="result?.found === true">
                    <div class="flex flex-col items-center gap-2 text-center">
                        <p class="text-muted-foreground text-lg font-semibold">
                            Job Order {{ result.number }}
                        </p>
                        <p class="text-[28px] leading-[1.2] font-semibold">
                            {{ result.stage }}
                        </p>
                        <p class="text-muted-foreground text-sm">
                            This page updates automatically.
                        </p>
                    </div>
                    <Button
                        variant="outline"
                        data-test="check-another-order-button"
                        @click="checkAnother"
                    >
                        Check another order
                    </Button>
                </template>

                <template v-else>
                    <div v-if="result?.found === false" class="flex flex-col gap-4">
                        <Alert variant="destructive">
                            <AlertCircle class="size-4" />
                            <AlertTitle>
                                We couldn't find that order
                            </AlertTitle>
                            <AlertDescription>
                                Check the job order number on your receipt and
                                try again.
                            </AlertDescription>
                        </Alert>
                    </div>

                    <div v-else class="flex flex-col items-center gap-2 text-center">
                        <h1 class="text-[28px] leading-[1.2] font-semibold">
                            Track Your Order
                        </h1>
                        <p class="text-muted-foreground text-sm">
                            Enter the job order number printed on your
                            receipt.
                        </p>
                    </div>

                    <form class="flex flex-col gap-4" @submit.prevent="lookup">
                        <div class="grid gap-2">
                            <Label for="tracking-number">
                                Job Order Number
                            </Label>
                            <Input
                                id="tracking-number"
                                v-model="numberInput"
                                placeholder="JO-2026-0001"
                                autocapitalize="characters"
                            />
                            <InputError :message="page.props.errors?.number" />
                        </div>
                        <Button
                            type="submit"
                            class="w-full"
                            :disabled="processing"
                            data-test="check-status-button"
                        >
                            <Spinner v-if="processing" class="mr-2" />
                            Check Status
                        </Button>
                    </form>
                </template>
            </CardContent>
        </Card>
    </div>
</template>

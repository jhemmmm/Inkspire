<script setup lang="ts">
import { Check, CircleDot, CircleX } from '@lucide/vue';
import { computed } from 'vue';

/**
 * The customer-facing progress ladder on the public tracking pages.
 *
 * Written for someone standing outside the shop holding a paper slip, not
 * for staff: every line says what is happening to their order in plain
 * words, and the step they are on is the loudest thing on the page.
 *
 * `step` is a position, from TrackingController::publicStageStep(). It is
 * an integer rather than a status name on purpose — the tracking response
 * must never carry the internal enum, and STEPS below must stay the same
 * length and order as that controller's PUBLIC_STAGE_ORDER.
 *
 * `step: null` means the order left the ladder — cancelled — which ends the
 * journey rather than sitting on a step.
 */
const props = defineProps<{
    step: number | null;
}>();

const STEPS = [
    {
        title: 'Order received',
        blurb: 'We have your order and we are getting it ready.',
    },
    {
        title: 'Queued for printing',
        blurb: 'Everything is approved and your order is lined up on the press.',
    },
    {
        title: 'Printing',
        blurb: 'Your order is being printed right now.',
    },
    {
        title: 'Ready for pickup',
        blurb: 'Come and collect it at the shop — bring this slip with you.',
    },
    {
        title: 'Picked up',
        blurb: 'Collected. Thank you for your order!',
    },
] as const;

const isCancelled = computed(() => props.step === null);

const currentIndex = computed(() => props.step ?? -1);

function state(index: number): 'done' | 'current' | 'upcoming' {
    if (index < currentIndex.value) {
        return 'done';
    }

    return index === currentIndex.value ? 'current' : 'upcoming';
}
</script>

<template>
    <div v-if="isCancelled" class="flex flex-col gap-3">
        <div
            class="border-destructive/40 bg-destructive/10 flex items-start gap-3 rounded-xl border p-4"
        >
            <CircleX class="text-destructive mt-0.5 size-5 shrink-0" />
            <div class="flex flex-col gap-1">
                <p class="font-semibold">This order was cancelled</p>
                <p class="text-muted-foreground text-sm">
                    Nothing further will be printed. If you think this is a
                    mistake, bring your slip to the shop counter and staff can
                    check it for you.
                </p>
            </div>
        </div>
    </div>

    <ol v-else class="flex flex-col">
        <li
            v-for="(step, index) in STEPS"
            :key="step.title"
            class="flex gap-3"
            :aria-current="state(index) === 'current' ? 'step' : undefined"
        >
            <!-- Marker column: the dot, plus the line joining it to the next
                 step. The last step has no trailing line. -->
            <div class="flex flex-col items-center">
                <span
                    class="flex size-7 shrink-0 items-center justify-center rounded-full border-2"
                    :class="{
                        'border-primary bg-primary text-primary-foreground':
                            state(index) === 'done',
                        'border-primary text-primary':
                            state(index) === 'current',
                        'border-border text-muted-foreground':
                            state(index) === 'upcoming',
                    }"
                >
                    <Check v-if="state(index) === 'done'" class="size-4" />
                    <CircleDot
                        v-else-if="state(index) === 'current'"
                        class="size-4"
                    />
                    <span v-else class="size-2 rounded-full bg-current" />
                </span>
                <span
                    v-if="index < STEPS.length - 1"
                    class="w-0.5 flex-1"
                    :class="
                        state(index) === 'done' ? 'bg-primary' : 'bg-border'
                    "
                />
            </div>

            <div
                class="flex flex-col gap-0.5"
                :class="index < STEPS.length - 1 ? 'pb-6' : ''"
            >
                <p
                    class="leading-tight"
                    :class="
                        state(index) === 'current'
                            ? 'text-base font-semibold'
                            : state(index) === 'done'
                              ? 'text-sm font-medium'
                              : 'text-muted-foreground text-sm'
                    "
                >
                    {{ step.title }}
                </p>
                <p
                    v-if="state(index) !== 'upcoming'"
                    class="text-muted-foreground text-sm"
                >
                    {{ step.blurb }}
                </p>
            </div>
        </li>
    </ol>
</template>

<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { ArrowRight, Check, CircleCheck, Clock } from '@lucide/vue';
import InkCard from '@/components/InkCard.vue';
import PublicNotice from '@/components/PublicNotice.vue';
import PublicPage from '@/components/PublicPage.vue';
import { Button } from '@/components/ui/button';
import { create } from '@/routes/public/orders';

interface ConfirmedJobOrder {
    number: string | null;
    description: string;
    trackingUrl: string;
}

defineProps<{
    state: 'pending' | 'confirmed' | 'expired';
    email?: string;
    items?: string[];
    confirmUrl?: string;
    jobOrders?: ConfirmedJobOrder[];
}>();
</script>

<template>
    <Head title="Confirm your order" />

    <PublicPage width="max-w-xl">
        <InkCard>
            <template v-if="state === 'pending'">
                <div class="px-6 pt-9 sm:px-10 sm:pt-11">
                    <div class="bg-primary mb-4 h-[3px] w-10 rounded-full" />
                    <h1
                        class="text-2xl leading-tight font-extrabold tracking-tight sm:text-3xl"
                    >
                        Confirm your order
                    </h1>
                    <p
                        class="text-muted-foreground mt-2 text-sm leading-relaxed sm:text-base"
                    >
                        {{ items?.length ?? 0 }} item{{
                            items?.length === 1 ? '' : 's'
                        }}
                        for
                        <span class="text-foreground font-semibold">
                            {{ email }} </span
                        >. Your order is not placed until you confirm.
                    </p>
                </div>

                <ol
                    class="border-border divide-border mx-6 mt-6 divide-y rounded-xl border sm:mx-10"
                >
                    <li
                        v-for="(item, index) in items"
                        :key="index"
                        class="flex items-start gap-3 px-4 py-3 text-sm"
                    >
                        <span
                            aria-hidden="true"
                            class="bg-accent text-accent-foreground flex size-6 shrink-0 items-center justify-center rounded-md text-xs font-bold tabular-nums"
                        >
                            {{ index + 1 }}
                        </span>
                        <span class="min-w-0 pt-0.5">{{ item }}</span>
                    </li>
                </ol>

                <Form
                    :action="confirmUrl"
                    method="post"
                    class="px-6 py-6 sm:px-10 sm:py-8"
                    v-slot="{ processing }"
                >
                    <Button
                        type="submit"
                        size="lg"
                        class="w-full"
                        :disabled="processing"
                        data-test="confirm-order-button"
                    >
                        <Check class="size-4" />
                        {{ processing ? 'Confirming…' : 'Confirm my order' }}
                    </Button>
                </Form>
            </template>

            <template v-else-if="state === 'confirmed'">
                <PublicNotice
                    :icon="CircleCheck"
                    tone="bg-success/15 text-success"
                    title="Your order is in"
                >
                    We emailed you a receipt. Use the tracking link to follow
                    each order, to approve a design and to pay once the shop
                    confirms the price.
                </PublicNotice>
                <ul class="divide-border border-border divide-y border-t">
                    <li
                        v-for="jobOrder in jobOrders"
                        :key="jobOrder.trackingUrl"
                        class="flex flex-wrap items-center justify-between gap-3 px-6 py-4 sm:px-10"
                    >
                        <div class="min-w-0">
                            <p class="font-bold tabular-nums">
                                {{ jobOrder.number }}
                            </p>
                            <p class="text-muted-foreground text-sm">
                                {{ jobOrder.description }}
                            </p>
                        </div>
                        <Button as-child variant="secondary">
                            <a
                                :href="jobOrder.trackingUrl"
                                data-test="track-order-link"
                            >
                                Track this order
                                <ArrowRight class="size-4" />
                            </a>
                        </Button>
                    </li>
                </ul>
            </template>

            <PublicNotice
                v-else
                :icon="Clock"
                tone="bg-muted text-muted-foreground"
                title="This link has expired"
            >
                The link is no longer valid, or the order was already cleaned
                up. You can place it again.
                <template #actions>
                    <Button as-child size="lg">
                        <Link :href="create()">
                            Order online
                            <ArrowRight class="size-4" />
                        </Link>
                    </Button>
                </template>
            </PublicNotice>
        </InkCard>
    </PublicPage>
</template>

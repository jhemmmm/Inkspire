<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { CircleCheck, Clock, MailCheck } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { home } from '@/routes';
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

    <div
        class="bg-muted text-foreground flex min-h-screen flex-col overflow-x-clip"
    >
        <header
            class="mx-auto flex w-full max-w-xl items-center px-4 py-4 sm:px-6 sm:py-5"
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
        </header>

        <main class="mx-auto w-full max-w-xl flex-1 px-4 pb-10 sm:px-6">
            <Card v-if="state === 'pending'">
                <CardHeader :icon="MailCheck">
                    <CardTitle>Confirm your order</CardTitle>
                    <CardDescription>
                        {{ items?.length ?? 0 }} item{{
                            items?.length === 1 ? '' : 's'
                        }}
                        for {{ email }}. Your order is not placed until you
                        confirm.
                    </CardDescription>
                </CardHeader>
                <CardContent class="grid gap-4">
                    <ul class="list-disc pl-5 text-sm">
                        <li v-for="(item, index) in items" :key="index">
                            {{ item }}
                        </li>
                    </ul>
                    <Form
                        :action="confirmUrl"
                        method="post"
                        v-slot="{ processing }"
                    >
                        <Button
                            type="submit"
                            size="lg"
                            :disabled="processing"
                            data-test="confirm-order-button"
                        >
                            {{
                                processing ? 'Confirming…' : 'Confirm my order'
                            }}
                        </Button>
                    </Form>
                </CardContent>
            </Card>

            <Card v-else-if="state === 'confirmed'">
                <CardHeader :icon="CircleCheck">
                    <CardTitle>Your order is in</CardTitle>
                    <CardDescription>
                        We emailed you a receipt. Use the tracking link to
                        follow each order, to approve a design and to pay once
                        the shop confirms the price.
                    </CardDescription>
                </CardHeader>
                <CardContent class="grid gap-4">
                    <ul class="divide-border divide-y">
                        <li
                            v-for="jobOrder in jobOrders"
                            :key="jobOrder.trackingUrl"
                            class="flex flex-wrap items-center justify-between gap-3 py-3"
                        >
                            <div class="min-w-0">
                                <p class="font-semibold tabular-nums">
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
                                </a>
                            </Button>
                        </li>
                    </ul>
                </CardContent>
            </Card>

            <Card v-else>
                <CardHeader :icon="Clock">
                    <CardTitle>This link has expired</CardTitle>
                    <CardDescription>
                        The link is no longer valid, or the order was already
                        cleaned up. You can place it again.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <Button as-child>
                        <Link :href="create()">Order online</Link>
                    </Button>
                </CardContent>
            </Card>
        </main>
    </div>
</template>

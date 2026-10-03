<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { ArrowRight, QrCode } from '@lucide/vue';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import OrderProgress from '@/components/OrderProgress.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    authErrorClass,
    authInputClass,
    authSubmitClass,
} from '@/layouts/auth/fields';
import { create as orderCreate } from '@/routes/public/orders';
import { show as trackingShow } from '@/routes/public/tracking';

/**
 * Squarefoot's public front door, built as the login's sibling: a white
 * panel and a royal-blue panel on one card, then two calm sections.
 *
 * Whoever lands here is usually holding a paper slip and wants to know if
 * their print is ready, so the blue tracker panel is the first thing on a
 * phone. Staff reach the portal privately, so nothing here links to sign-in
 * or the queue board (WelcomePageTest).
 *
 * Every claim is backed by the app. The price board is PricingDatabaseSeeder's
 * walk-in list (retired rows left out), the tarp sizes are
 * SpecificationOptionSeeder's, and the rules describe the queue, file check,
 * design review and payment code as it stands. Prices and config values
 * (file types, DPI floor, rush fee) stay off on purpose: they change, and
 * the counter quotes them.
 *
 * The photos are Unsplash License placeholders until the shop has its own:
 * banner-printing (CYrYxz-uvE4), blank-billboard (Dnkr_lmdKi8),
 * sticker-printing (4kv2XrHkUoY), hanging-sign (KXwFJV2Nkyw), photo-printer
 * (k1WVgtr2zDA) and cmyk-proof (eWuUwZWuWvE), all unsplash.com/photos/{id}.
 * They are decorative, so every one has an empty alt.
 */

interface PriceUnit {
    visible: string;
    spoken: string;
}

interface PriceBoardItem {
    name: string;
    unit?: PriceUnit;
}

interface PriceBoardGroup {
    title: string;
    unit: PriceUnit | null;
    image: { src: string; width: number; height: number; position: string };
    items: readonly PriceBoardItem[];
}

interface OrderRule {
    label: string;
    body: string;
}

const page = usePage();
const trackingNumber = ref('');
const isOpeningOrder = ref(false);

/** Follows JobOrder's JO-{year}-{0000} format, so the example never looks stale. */
const placeholderNumber = `JO-${new Date().getFullYear()}-0001`;

/** TrackJobOrderRequest's "Enter a job order number like …" message. */
const numberError = computed(() => page.props.errors?.number);

/**
 * Open the tracking page for the typed number. The field displays
 * upper-case and TrackJobOrderRequest's regex is case-sensitive, so the
 * value is upper-cased to match what the customer sees; spaces and the
 * en/em dashes phone keyboards substitute become the hyphen it expects.
 *
 * A number in the wrong shape redirects back here with an error, so state
 * is kept on errors only: the typed value survives and the message shows
 * under the field.
 */
function trackOrder(): void {
    const number = trackingNumber.value
        .trim()
        .toUpperCase()
        .replace(/[\s–—-]+/g, '-');

    if (number === '') {
        return;
    }

    router.get(
        trackingShow.url(),
        { number },
        {
            preserveState: 'errors',
            onStart: () => (isOpeningOrder.value = true),
            onError: () =>
                document.getElementById('welcome-tracking-number')?.focus(),
            onFinish: () => (isOpeningOrder.value = false),
        },
    );
}

const TARP_SIZES: readonly string[] = ['2x3', '3x5', '3x6', '4x8', '6x10'];

const PER_SQ_FT: PriceUnit = {
    visible: 'per sq ft',
    spoken: 'per square foot',
};
const PER_PIECE: PriceUnit = { visible: 'per piece', spoken: 'per piece' };

const PRICE_BOARD: readonly PriceBoardGroup[] = [
    {
        title: 'Tarp',
        unit: PER_SQ_FT,
        image: {
            src: '/images/blank-billboard.webp',
            width: 800,
            height: 533,
            position: 'object-center',
        },
        items: [
            { name: 'Tarpaulin' },
            { name: 'Blackout' },
            { name: 'Tarp with lamination' },
            { name: 'Tarp on sintraboard or foamboard' },
            { name: 'Tarp or blackout with wood or metal frame' },
        ],
    },
    {
        title: 'Stickers',
        unit: PER_SQ_FT,
        image: {
            src: '/images/sticker-printing.webp',
            width: 800,
            height: 1196,
            position: 'object-[50%_60%]',
        },
        items: [
            { name: 'Printed vinyl sticker' },
            { name: 'Pre-cut vinyl sticker' },
            { name: 'Cut vinyl sticker' },
            { name: 'Sticker with lamination' },
            { name: 'Transparent sticker' },
            { name: 'Frosted sticker, printed or cut' },
            { name: 'Perforated sticker' },
            { name: 'Reflectorized sticker, printed or cut' },
            { name: 'Prismatic reflective sticker' },
        ],
    },
    {
        title: 'Signs and boards',
        unit: PER_SQ_FT,
        image: {
            src: '/images/hanging-sign.webp',
            width: 800,
            height: 1093,
            position: 'object-[50%_40%]',
        },
        items: [
            { name: 'Panaflex print or UV print' },
            { name: 'Panaflex with laminate' },
            { name: 'Backlit print' },
            { name: '3mm acrylic sandwich' },
            { name: 'Sticker on sintraboard, one side or back to back' },
            { name: 'Sticker on foamboard' },
            { name: 'Sticker on acrylic, printed or cut' },
            { name: 'Sticker on magnet' },
        ],
    },
    {
        title: 'Stands, photo and mugs',
        unit: null,
        image: {
            src: '/images/photo-printer.webp',
            width: 800,
            height: 1202,
            position: 'object-[50%_45%]',
        },
        items: [
            { name: 'Pull-up banner, big or small', unit: PER_PIECE },
            { name: 'X-stand banner', unit: PER_PIECE },
            { name: 'Mug print', unit: PER_PIECE },
            { name: 'Matte photopaper', unit: PER_SQ_FT },
            { name: 'Canvas, print only or framed', unit: PER_SQ_FT },
        ],
    },
];

const ORDER_RULES: readonly OrderRule[] = [
    {
        label: 'Order online',
        body: 'Send your order from this site. We email you a link to confirm it, and your order goes straight to the shop.',
    },
    {
        label: 'Walk in',
        body: 'Or get a queue number at the counter. Rush jobs get an R number and are called first.',
    },
    {
        label: 'Your file',
        body: 'Bring a print-ready file. We check image files against the size you ordered. If one would print blurry at that size, one of our artists works on it first.',
    },
    {
        label: 'No file yet',
        body: 'Sit down with one of our artists and lay it out together.',
    },
    {
        label: 'Approval',
        body: 'If our artist made or fixed your layout, nothing prints until you approve it. Approve it at the counter or from a link we can email you. You can ask for changes instead.',
    },
    {
        label: 'Payment',
        body: 'Pay by cash, bank transfer, GCash or Maya. For GCash and Maya, scan the QR code we show you at the counter. Pay in full, or start with a down payment.',
    },
    {
        label: 'Tracking',
        body: 'Your slip has your job order number and a QR code. Check your order on this page any time. You don’t need an account.',
    },
    {
        label: 'Pickup',
        body: 'When your order shows Ready for pickup, come to the shop and bring your slip.',
    },
];

/** Keyboard focus on the light ground: a solid ring-blue outline. */
const linkFocusClass =
    'rounded-md transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring motion-reduce:transition-none';

/** The login's soft royal-blue-tinted lift, shared by the two big cards. */
const panelShadowClass =
    'shadow-[0_20px_60px_rgba(0,40,142,0.12),0_4px_16px_rgba(0,0,0,0.08)]';
</script>

<template>
    <div
        class="bg-muted text-foreground flex min-h-screen flex-col overflow-x-clip"
    >
        <Head title="Squarefoot Graphics & Ads">
            <meta
                head-key="description"
                name="description"
                content="Tarpaulin, Panaflex, sticker and signage printing, priced by the square foot. Check your job order with the number on your slip."
            />
        </Head>

        <header
            class="mx-auto flex w-full max-w-6xl items-center justify-between gap-4 px-4 py-4 sm:px-6 sm:py-5"
        >
            <img
                src="/logo.png"
                alt="Inkspire"
                width="824"
                height="303"
                class="h-8 w-auto sm:h-9"
            />
            <nav
                aria-label="On this page"
                class="flex items-center gap-1 text-sm font-semibold"
            >
                <a
                    href="#what-we-print"
                    :class="[
                        linkFocusClass,
                        'text-muted-foreground hover:text-foreground hidden px-3 py-2 md:inline-flex',
                    ]"
                >
                    What we print
                </a>
                <a
                    href="#how-to-order"
                    :class="[
                        linkFocusClass,
                        'text-muted-foreground hover:text-foreground hidden px-3 py-2 md:inline-flex',
                    ]"
                >
                    How to order
                </a>
                <a
                    :href="orderCreate.url()"
                    data-test="welcome-order-link"
                    :class="[
                        linkFocusClass,
                        'text-muted-foreground hover:text-foreground max-sm:bg-card max-sm:text-primary max-sm:border-border px-3 py-2 whitespace-nowrap max-sm:rounded-full max-sm:border max-sm:px-3.5 max-sm:py-1.5 max-sm:shadow-xs',
                    ]"
                >
                    Order online
                </a>
                <a
                    href="#track"
                    data-test="welcome-track-link"
                    :class="[
                        linkFocusClass,
                        'bg-card text-primary border-border hover:border-primary/40 ml-1 hidden items-center rounded-full border px-3.5 py-1.5 whitespace-nowrap shadow-xs sm:inline-flex',
                    ]"
                >
                    Track an order
                </a>
            </nav>
        </header>

        <main class="flex-1">
            <div class="mx-auto w-full max-w-6xl px-4 sm:px-6">
                <div
                    :class="[
                        panelShadowClass,
                        'bg-card grid overflow-hidden rounded-[20px] lg:grid-cols-[minmax(0,1.3fr)_minmax(0,1fr)]',
                    ]"
                >
                    <!-- White panel: what this is, and what the customer gets back. -->
                    <div
                        class="relative flex min-w-0 flex-col px-6 pt-8 sm:px-10 sm:pt-12 lg:px-14 lg:pt-14"
                    >
                        <div
                            aria-hidden="true"
                            class="absolute inset-x-0 top-0 grid h-1 grid-cols-4"
                        >
                            <span class="bg-ink-cyan" />
                            <span class="bg-ink-magenta" />
                            <span class="bg-ink-yellow" />
                            <span class="bg-ink-key" />
                        </div>
                        <div
                            class="bg-primary mb-5 h-[3px] w-10 rounded-full"
                        />
                        <h1
                            class="max-w-[30rem] text-[1.75rem] leading-[1.1] font-extrabold tracking-tight text-balance sm:text-[2.25rem] lg:text-[2.625rem]"
                        >
                            Know where your print is.
                        </h1>
                        <p
                            class="text-muted-foreground mt-3 max-w-[32rem] text-sm leading-relaxed lg:text-base"
                        >
                            Squarefoot Graphics &amp; Ads prints tarpaulin,
                            Panaflex, stickers and signage. Every order gets a
                            job order number, so you can check on it here any
                            time instead of calling the shop.
                        </p>
                        <div class="mt-6">
                            <Button as-child size="lg">
                                <a
                                    :href="orderCreate.url()"
                                    data-test="welcome-order-button"
                                >
                                    Order online
                                    <ArrowRight class="size-4" />
                                </a>
                            </Button>
                        </div>

                        <!--
                            The tracking page's own card (Tracking.vue's header
                            over the real OrderProgress) laid on a photo and
                            cropped by the panel edge. Sample data, so the whole
                            block is hidden from screen readers.
                        -->
                        <div
                            aria-hidden="true"
                            class="relative mt-8 h-72 select-none sm:mt-10 sm:h-80"
                        >
                            <img
                                src="/images/banner-printing.webp"
                                alt=""
                                width="1200"
                                height="800"
                                class="absolute inset-0 size-full rounded-t-2xl object-cover object-[70%_50%]"
                            />
                            <div
                                class="absolute inset-0 rounded-t-2xl ring-1 ring-black/5 ring-inset"
                            />
                            <div
                                class="bg-card border-border absolute top-16 left-4 w-[min(calc(100%-3rem),21rem)] rounded-xl border px-5 pt-5 pb-6 shadow-[0_18px_40px_rgba(0,20,70,0.22),0_2px_6px_rgba(0,0,0,0.08)] sm:top-10 sm:left-6"
                            >
                                <div
                                    class="mb-5 flex flex-col items-center gap-0.5 text-center"
                                >
                                    <p
                                        class="text-muted-foreground text-xs font-semibold"
                                    >
                                        Job Order
                                        <span class="tabular-nums">
                                            {{ placeholderNumber }}
                                        </span>
                                    </p>
                                    <p class="text-2xl leading-tight font-bold">
                                        Printing
                                    </p>
                                </div>
                                <OrderProgress :step="2" />
                            </div>
                        </div>
                    </div>

                    <!-- Royal-blue tracker panel: first until the hero splits, then right. -->
                    <section
                        id="track"
                        aria-labelledby="track-heading"
                        class="bg-primary text-primary-foreground flex scroll-mt-4 flex-col items-center justify-center px-6 py-9 text-center max-lg:order-first sm:px-10 sm:py-12 lg:px-14"
                    >
                        <span
                            class="inline-flex items-center rounded-full border border-white/25 bg-white/15 px-3.5 py-1 text-[10px] font-bold tracking-widest text-white/90 uppercase"
                        >
                            No account needed
                        </span>
                        <h2
                            id="track-heading"
                            class="mt-4 text-[1.625rem] leading-tight font-extrabold tracking-tight sm:text-[1.875rem]"
                        >
                            Is my print ready?
                        </h2>
                        <p
                            id="welcome-tracking-help"
                            class="mt-1.5 max-w-[20rem] text-sm text-white/75"
                        >
                            Type the job order number from your slip or receipt.
                        </p>

                        <form
                            class="mt-7 flex w-full max-w-[20rem] flex-col gap-2 text-left"
                            @submit.prevent="trackOrder"
                        >
                            <Label
                                for="welcome-tracking-number"
                                class="text-[11px] font-bold tracking-widest text-white/70 uppercase"
                            >
                                Job order number
                            </Label>
                            <Input
                                id="welcome-tracking-number"
                                v-model="trackingNumber"
                                :placeholder="placeholderNumber"
                                required
                                autocomplete="off"
                                autocapitalize="characters"
                                spellcheck="false"
                                enterkeyhint="go"
                                :aria-invalid="numberError ? 'true' : undefined"
                                :aria-describedby="
                                    numberError
                                        ? 'welcome-tracking-help welcome-tracking-error'
                                        : 'welcome-tracking-help'
                                "
                                data-test="welcome-tracking-input"
                                :class="[
                                    authInputClass,
                                    'h-12 text-lg font-bold tracking-wide uppercase tabular-nums placeholder:font-semibold placeholder:text-white/45 aria-invalid:border-red-300 md:text-lg',
                                ]"
                            />
                            <!-- Always mounted, so a screen reader announces
                                 the message when it appears. -->
                            <div role="alert">
                                <InputError
                                    id="welcome-tracking-error"
                                    :message="numberError"
                                    :class="authErrorClass"
                                />
                            </div>
                            <Button
                                type="submit"
                                size="lg"
                                :disabled="isOpeningOrder"
                                data-test="welcome-tracking-submit"
                                :class="[
                                    authSubmitClass,
                                    'mt-2 h-11 font-bold focus-visible:ring-white/70 motion-reduce:transition-none',
                                ]"
                            >
                                <template v-if="isOpeningOrder">
                                    Checking…
                                </template>
                                <template v-else>
                                    Track my order
                                    <ArrowRight class="size-4" />
                                </template>
                            </Button>
                        </form>

                        <p
                            class="mt-6 flex max-w-[20rem] items-start gap-2.5 text-left text-[13px] leading-snug text-white/75"
                        >
                            <QrCode
                                aria-hidden="true"
                                class="mt-0.5 size-5 shrink-0 stroke-[1.75]"
                            />
                            The number is printed under the QR code on your
                            slip. Or scan the code to open your order directly.
                        </p>
                    </section>
                </div>
            </div>

            <!-- The price board. Names and units only; the counter quotes prices. -->
            <section
                id="what-we-print"
                aria-labelledby="what-we-print-heading"
                class="mx-auto w-full max-w-6xl scroll-mt-4 px-4 pt-16 sm:px-6 lg:pt-24"
            >
                <div
                    class="grid gap-3 lg:grid-cols-[minmax(0,1fr)_minmax(0,28rem)] lg:items-end lg:gap-16"
                >
                    <div>
                        <div
                            class="bg-primary mb-4 h-[3px] w-10 rounded-full"
                        />
                        <h2
                            id="what-we-print-heading"
                            class="text-2xl leading-tight font-extrabold tracking-tight sm:text-3xl"
                        >
                            What we print
                        </h2>
                    </div>
                    <p
                        class="text-muted-foreground text-sm leading-relaxed lg:text-base"
                    >
                        Most of what we print is charged by area. A 3x5 ft tarp
                        is 15 sq ft. Stands and mugs are priced per piece. Bring
                        the width and height, and we’ll quote you at the
                        counter.
                    </p>
                </div>

                <div class="mt-8 grid gap-5 sm:grid-cols-2 xl:grid-cols-4">
                    <article
                        v-for="group in PRICE_BOARD"
                        :key="group.title"
                        class="bg-card border-border flex flex-col overflow-hidden rounded-2xl border shadow-[0_1px_2px_rgba(0,0,0,0.04),0_10px_30px_rgba(0,40,142,0.07)]"
                    >
                        <img
                            :src="group.image.src"
                            alt=""
                            :width="group.image.width"
                            :height="group.image.height"
                            loading="lazy"
                            :class="[
                                group.image.position,
                                'aspect-[16/10] w-full object-cover',
                            ]"
                        />
                        <div
                            class="flex items-baseline justify-between gap-3 px-5 pt-4 pb-1"
                        >
                            <h3 class="text-lg font-extrabold tracking-tight">
                                {{ group.title }}
                            </h3>
                            <span
                                v-if="group.unit"
                                class="text-muted-foreground shrink-0 text-xs font-semibold"
                            >
                                <span aria-hidden="true">
                                    {{ group.unit.visible }}
                                </span>
                                <span class="sr-only">
                                    {{ group.unit.spoken }}
                                </span>
                            </span>
                        </div>
                        <ul class="px-5 pb-4 text-sm leading-snug">
                            <li
                                v-for="item in group.items"
                                :key="item.name"
                                class="border-border flex items-baseline justify-between gap-3 border-b py-2 last:border-b-0"
                            >
                                <span class="min-w-0">{{ item.name }}</span>
                                <span
                                    v-if="item.unit"
                                    class="text-muted-foreground shrink-0 text-xs"
                                >
                                    <span aria-hidden="true">
                                        {{ item.unit.visible }}
                                    </span>
                                    <span class="sr-only">
                                        {{ item.unit.spoken }}
                                    </span>
                                </span>
                            </li>
                        </ul>
                    </article>
                </div>

                <dl
                    class="bg-card border-border mt-5 grid overflow-hidden rounded-2xl border sm:grid-cols-3"
                >
                    <div
                        class="border-border border-b p-5 sm:border-r sm:border-b-0"
                    >
                        <dt class="text-sm font-bold">Standard tarp sizes</dt>
                        <dd
                            class="text-primary mt-1 text-lg font-extrabold tracking-tight tabular-nums"
                        >
                            <span aria-hidden="true">
                                {{ TARP_SIZES.join(' · ') }}&nbsp;ft
                            </span>
                            <span class="sr-only">
                                {{ TARP_SIZES.join(', ') }} feet
                            </span>
                        </dd>
                        <dd class="text-muted-foreground mt-0.5 text-sm">
                            We print custom sizes too.
                        </dd>
                    </div>
                    <div
                        class="border-border border-b p-5 sm:border-r sm:border-b-0"
                    >
                        <dt class="text-sm font-bold">Installation</dt>
                        <dd
                            class="text-muted-foreground mt-1 text-sm leading-relaxed"
                        >
                            We can install frosted and perforated stickers and
                            laminated tarp for you.
                        </dd>
                    </div>
                    <div class="p-5">
                        <dt class="text-sm font-bold">Plain media</dt>
                        <dd
                            class="text-muted-foreground mt-1 text-sm leading-relaxed"
                        >
                            Unprinted tarp, blackout, Panaflex, sticker vinyl
                            and photopaper. Tarp also comes by the roll and
                            sintraboard by the sheet.
                        </dd>
                    </div>
                </dl>
            </section>

            <section
                id="how-to-order"
                aria-labelledby="how-to-order-heading"
                class="mx-auto w-full max-w-6xl scroll-mt-4 px-4 py-16 sm:px-6 lg:py-24"
            >
                <div
                    class="bg-card grid overflow-hidden rounded-[20px] shadow-[0_20px_60px_rgba(0,40,142,0.08),0_4px_16px_rgba(0,0,0,0.05)] lg:grid-cols-[minmax(0,20rem)_minmax(0,1fr)]"
                >
                    <div
                        class="border-border relative flex flex-col border-b px-6 pt-9 pb-7 sm:px-10 lg:border-r lg:border-b-0 lg:py-12"
                    >
                        <div
                            aria-hidden="true"
                            class="absolute inset-x-0 top-0 grid h-1 grid-cols-4"
                        >
                            <span class="bg-ink-cyan" />
                            <span class="bg-ink-magenta" />
                            <span class="bg-ink-yellow" />
                            <span class="bg-ink-key" />
                        </div>
                        <div
                            class="bg-primary mb-4 h-[3px] w-10 rounded-full"
                        />
                        <h2
                            id="how-to-order-heading"
                            class="text-2xl leading-tight font-extrabold tracking-tight sm:text-3xl"
                        >
                            How to order
                        </h2>
                        <p
                            class="text-muted-foreground mt-2 text-sm leading-relaxed"
                        >
                            From the counter to pickup.
                        </p>
                        <img
                            src="/images/cmyk-proof.webp"
                            alt=""
                            width="800"
                            height="1203"
                            loading="lazy"
                            class="mt-6 h-44 w-full rounded-xl object-cover sm:h-56 lg:h-auto lg:min-h-0 lg:flex-1"
                        />
                    </div>
                    <dl class="divide-border divide-y px-6 sm:px-10">
                        <div
                            v-for="rule in ORDER_RULES"
                            :key="rule.label"
                            class="grid gap-1 py-4 sm:grid-cols-[8rem_minmax(0,1fr)] sm:gap-6 sm:py-5"
                        >
                            <dt class="text-primary text-sm font-bold">
                                {{ rule.label }}
                            </dt>
                            <dd
                                class="text-muted-foreground text-sm leading-relaxed"
                            >
                                {{ rule.body }}
                            </dd>
                        </div>
                    </dl>
                </div>
            </section>
        </main>

        <footer class="border-border border-t">
            <div
                class="mx-auto flex w-full max-w-6xl flex-col gap-4 px-4 py-8 text-sm sm:flex-row sm:items-center sm:justify-between sm:px-6"
            >
                <div>
                    <p class="font-bold">Squarefoot Graphics &amp; Ads</p>
                    <p class="text-muted-foreground mt-0.5 text-xs">
                        Job orders and tracking run on Inkspire.
                    </p>
                </div>
                <nav
                    aria-label="Footer"
                    class="text-muted-foreground flex flex-wrap gap-x-6 gap-y-2 font-semibold"
                >
                    <a
                        :href="orderCreate.url()"
                        :class="[linkFocusClass, 'hover:text-foreground']"
                    >
                        Order online
                    </a>
                    <a
                        href="#track"
                        :class="[linkFocusClass, 'hover:text-foreground']"
                    >
                        Track an order
                    </a>
                    <a
                        href="#what-we-print"
                        :class="[linkFocusClass, 'hover:text-foreground']"
                    >
                        What we print
                    </a>
                    <a
                        href="#how-to-order"
                        :class="[linkFocusClass, 'hover:text-foreground']"
                    >
                        How to order
                    </a>
                </nav>
            </div>
        </footer>
    </div>
</template>

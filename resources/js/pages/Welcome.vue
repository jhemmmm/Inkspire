<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    ArrowRight,
    Banknote,
    CircleCheckBig,
    ClipboardList,
    CreditCard,
    FileCheck2,
    Frame,
    IdCard,
    Image as ImageIcon,
    Palette,
    PencilRuler,
    Printer,
    QrCode,
    Search,
    Signpost,
    Sticker,
    Store,
    Zap,
} from '@lucide/vue';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { show as trackingShow } from '@/routes/public/tracking';

/**
 * The shop's public front door.
 *
 * Two audiences, in this order: a customer holding a job order number who
 * wants to know where their print is, and a staff member signing in. The
 * customer comes first because they are the ones arriving cold — staff know
 * where the login is.
 */
const trackingNumber = ref('');

function trackOrder(): void {
    const number = trackingNumber.value.trim();

    if (number === '') {
        return;
    }

    router.get(trackingShow.url(), { number });
}

/**
 * Sample data for the hero artwork only. It is decorative — the whole block
 * is `aria-hidden` so a screen reader never reads out a job order that does
 * not exist.
 */
const HERO_TIMELINE = [
    { label: 'Queued at the counter', state: 'done' },
    { label: 'Design approved by you', state: 'done' },
    { label: 'Printing', state: 'done' },
    { label: 'Quality check', state: 'current' },
    { label: 'Ready for pickup', state: 'todo' },
] as const;

const PROMISES = [
    { icon: Zap, label: 'Rush lane for urgent jobs' },
    { icon: QrCode, label: 'Track by QR — no phone calls' },
    { icon: Banknote, label: 'Cash, transfer, GCash or Maya' },
] as const;

const SERVICES = [
    {
        icon: Frame,
        title: 'Tarpaulins & banners',
        body: 'Wide-format prints for events, promos and storefronts, at the size you need.',
    },
    {
        icon: Sticker,
        title: 'Stickers & decals',
        body: 'Vinyl stickers, labels and window decals cut to your artwork.',
    },
    {
        icon: IdCard,
        title: 'Cards & stationery',
        body: 'Calling cards, IDs and loyalty cards, printed and finished in house.',
    },
    {
        icon: Signpost,
        title: 'Signage & panels',
        body: 'Shop, office and event signage built to your measurements.',
    },
    {
        icon: ImageIcon,
        title: 'Photo & poster printing',
        body: 'Posters, photo prints and mounted panels for display.',
    },
    {
        icon: Palette,
        title: 'Layout & design',
        body: 'No file yet? Sit down with one of our artists and design it together.',
    },
] as const;

const PROOF_POINTS = [
    {
        icon: FileCheck2,
        title: 'Checked before it prints',
        body: 'Every file is measured against the size you ordered, so nothing prints soft.',
    },
    {
        icon: QrCode,
        title: 'You approve the artwork',
        body: 'Scan your slip, see the design, and say yes before the press runs.',
    },
    {
        icon: Zap,
        title: 'Rush lane available',
        body: 'Urgent jobs get their own queue and are called ahead of the regular lane.',
    },
    {
        icon: Banknote,
        title: 'Pay how you like',
        body: 'Cash, bank transfer, GCash or Maya, with a receipt for every payment.',
    },
] as const;

const PROCESS = [
    {
        icon: ClipboardList,
        title: 'Walk in and queue',
        body: 'Take a number at the counter. Rush jobs get their own lane and are called first.',
    },
    {
        icon: PencilRuler,
        title: 'Bring a file, or design it here',
        body: 'Hand over a print-ready file, or work it out on the spot with one of our artists.',
    },
    {
        icon: QrCode,
        title: 'Approve before we print',
        body: 'Scan the QR on your slip to see the design. Nothing goes on the press until you say so.',
    },
    {
        icon: Printer,
        title: 'Printing and quality check',
        body: 'Your order goes on the press, then gets checked before it leaves the shop.',
    },
    {
        icon: CreditCard,
        title: 'Pay how you like',
        body: 'Cash, bank transfer, GCash or Maya. Approved accounts can take terms and settle later.',
    },
    {
        icon: Store,
        title: 'Collect it',
        body: "We mark it ready the moment it's done, so you never have to ring up and ask.",
    },
] as const;
</script>

<template>
    <Head title="Inkspire — Squarefoot Graphics & Ads" />

    <div
        class="bg-background text-foreground flex min-h-screen flex-col overflow-x-clip"
    >
        <header
            class="border-border/60 bg-background/80 sticky top-0 z-50 border-b backdrop-blur"
        >
            <div
                class="mx-auto flex w-full max-w-6xl items-center justify-between gap-4 px-6 py-3"
            >
                <!-- The wordmark bakes "spire" in near-black, so it
                     disappears on a dark header. Flatten it to white there. -->
                <img
                    src="/logo.png"
                    alt="Inkspire"
                    class="h-8 w-auto object-contain dark:brightness-0 dark:invert"
                />

                <nav class="flex items-center gap-1 sm:gap-2">
                    <a
                        href="#what-we-print"
                        class="text-muted-foreground hover:text-foreground hidden rounded-md px-3 py-2 text-sm font-medium transition-colors md:inline-flex"
                    >
                        What we print
                    </a>
                    <a
                        href="#how-it-works"
                        class="text-muted-foreground hover:text-foreground hidden rounded-md px-3 py-2 text-sm font-medium transition-colors md:inline-flex"
                    >
                        How it works
                    </a>
                    <Button as-child size="sm" data-test="welcome-track-link">
                        <a href="#track">
                            Track my order
                            <ArrowRight class="size-4" />
                        </a>
                    </Button>
                </nav>
            </div>
        </header>

        <main class="flex-1">
            <!-- Hero: the one thing a customer actually came here to do,
                 next to a picture of what they will get back. -->
            <section class="border-border/60 relative border-b">
                <!-- Decorative background: a faint grid, faded out from the
                     bottom so it never competes with the copy. -->
                <div
                    aria-hidden="true"
                    class="pointer-events-none absolute inset-0 overflow-hidden"
                >
                    <div
                        class="from-primary/10 absolute inset-0 bg-gradient-to-b to-transparent"
                    ></div>
                    <div
                        class="absolute inset-0 bg-[linear-gradient(to_right,var(--border)_1px,transparent_1px),linear-gradient(to_bottom,var(--border)_1px,transparent_1px)] [background-size:56px_56px] opacity-50"
                    ></div>
                    <div
                        class="to-background absolute inset-x-0 bottom-0 h-40 bg-gradient-to-b from-transparent"
                    ></div>
                    <div
                        class="bg-primary/15 absolute -top-24 -right-24 size-96 rounded-full blur-3xl"
                    ></div>
                </div>

                <div
                    class="relative mx-auto grid w-full max-w-6xl gap-12 px-6 py-16 lg:grid-cols-[1.05fr_1fr] lg:items-center lg:gap-16 lg:py-24"
                >
                    <div class="flex flex-col gap-6">
                        <span
                            class="bg-accent text-accent-foreground border-primary/10 flex w-fit items-center gap-2 rounded-full border px-3 py-1 text-xs font-bold tracking-[0.08em] uppercase"
                        >
                            <Printer class="size-3.5" />
                            Squarefoot Graphics &amp; Ads
                        </span>

                        <h1
                            class="text-4xl leading-[1.05] font-extrabold tracking-tight text-balance sm:text-5xl lg:text-6xl"
                        >
                            Printing you can
                            <span class="text-primary">follow</span>, from the
                            counter to the shelf.
                        </h1>

                        <p
                            class="text-muted-foreground max-w-prose text-base leading-relaxed sm:text-lg"
                        >
                            Tarpaulins, stickers, cards and signage — designed,
                            printed and finished in house. Every order gets a
                            number you can check any time, so you never have to
                            ring up and ask if it's ready.
                        </p>

                        <div
                            id="track"
                            class="bg-card border-border shadow-primary/5 flex scroll-mt-24 flex-col gap-3 rounded-2xl border p-5 shadow-lg"
                        >
                            <div class="flex flex-col gap-1">
                                <Label
                                    for="welcome-tracking-number"
                                    class="text-base font-semibold"
                                >
                                    Where is my order?
                                </Label>
                                <p class="text-muted-foreground text-sm">
                                    Enter the job order number from your receipt
                                    or slip.
                                </p>
                            </div>
                            <form
                                class="flex flex-col gap-2 sm:flex-row"
                                @submit.prevent="trackOrder"
                            >
                                <div class="relative flex-1">
                                    <Search
                                        class="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2"
                                    />
                                    <Input
                                        id="welcome-tracking-number"
                                        v-model="trackingNumber"
                                        class="h-11 pl-9"
                                        placeholder="JO-2026-1234"
                                        autocomplete="off"
                                        data-test="welcome-tracking-input"
                                    />
                                </div>
                                <Button
                                    type="submit"
                                    size="lg"
                                    class="h-11"
                                    data-test="welcome-tracking-submit"
                                >
                                    Track my order
                                    <ArrowRight class="size-4" />
                                </Button>
                            </form>
                            <p
                                class="text-muted-foreground flex items-start gap-2 text-sm"
                            >
                                <QrCode class="mt-0.5 size-4 shrink-0" />
                                <span>
                                    Got a QR code on your slip? Just scan it —
                                    it opens the same page, no typing.
                                </span>
                            </p>
                        </div>

                        <ul
                            class="text-muted-foreground flex flex-wrap gap-x-6 gap-y-2 text-sm"
                        >
                            <li
                                v-for="promise in PROMISES"
                                :key="promise.label"
                                class="flex items-center gap-2"
                            >
                                <component
                                    :is="promise.icon"
                                    class="text-primary size-4"
                                />
                                {{ promise.label }}
                            </li>
                        </ul>
                    </div>

                    <!--
                        Hero photograph: large-format roll printing, which is
                        what most of the price list actually is (tarpaulin,
                        blackout, panaflex, sticker vinyl). Self-hosted rather
                        than hotlinked so the page does not depend on a third
                        party staying up.

                        Source: Unsplash (unsplash.com/photos/bd429a57f115),
                        Unsplash License — free for commercial use, no
                        attribution required. Swap in a photo of the actual
                        shop when there is one; nothing else has to change.

                        Intrinsic width/height are set so the browser reserves
                        the space before the image loads and the copy beside it
                        does not jump (CLS).
                    -->
                    <div class="relative mx-auto w-full max-w-xl lg:max-w-none">
                        <div
                            aria-hidden="true"
                            class="bg-primary/20 absolute -inset-x-4 -top-6 h-40 rounded-full blur-3xl"
                        ></div>
                        <img
                            src="/images/large-format-printing.jpg"
                            alt="A wide-format printer running a full-colour banner through the press"
                            width="1400"
                            height="933"
                            fetchpriority="high"
                            class="border-border relative w-full rounded-[28px] border object-cover shadow-xl"
                        />
                    </div>
                </div>
            </section>

            <section
                id="what-we-print"
                class="mx-auto w-full max-w-6xl scroll-mt-20 px-6 py-16 lg:py-20"
            >
                <div class="mb-10 flex flex-col gap-2">
                    <span
                        class="text-primary text-xs font-bold tracking-[0.12em] uppercase"
                    >
                        What we print
                    </span>
                    <h2
                        class="text-2xl leading-tight font-bold tracking-tight sm:text-3xl"
                    >
                        Large format, small format, and the design in between
                    </h2>
                    <p class="text-muted-foreground max-w-prose">
                        Everything below is produced in house, so the same
                        people who lay it out are the ones who print it.
                    </p>
                </div>

                <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    <div
                        v-for="service in SERVICES"
                        :key="service.title"
                        class="group bg-card border-border hover:border-primary/40 flex flex-col gap-4 rounded-2xl border p-6 shadow-sm transition-colors"
                    >
                        <span
                            class="from-primary to-primary/70 text-primary-foreground flex size-12 items-center justify-center rounded-xl bg-gradient-to-br shadow-sm"
                        >
                            <component :is="service.icon" class="size-6" />
                        </span>
                        <h3 class="font-semibold">{{ service.title }}</h3>
                        <p
                            class="text-muted-foreground text-sm leading-relaxed"
                        >
                            {{ service.body }}
                        </p>
                    </div>
                </div>
            </section>

            <section class="border-border/60 border-y">
                <div
                    class="mx-auto grid w-full max-w-6xl gap-10 px-6 py-16 lg:grid-cols-2 lg:items-center lg:gap-14 lg:py-20"
                >
                    <!--
                        Source: Unsplash (unsplash.com/photos/8a2fa686963a),
                        Unsplash License — free for commercial use, no
                        attribution required.
                    -->
                    <img
                        src="/images/printing-press.jpg"
                        alt="A commercial printing press running sheets at speed"
                        width="1200"
                        height="801"
                        loading="lazy"
                        class="border-border w-full rounded-[28px] border object-cover shadow-lg"
                    />

                    <div class="flex flex-col gap-6">
                        <span
                            class="text-primary text-xs font-bold tracking-[0.12em] uppercase"
                        >
                            About Squarefoot
                        </span>
                        <h2
                            class="text-2xl leading-tight font-bold tracking-tight text-balance sm:text-3xl lg:text-4xl"
                        >
                            Printed in house, start to finish.
                        </h2>
                        <p
                            class="text-muted-foreground max-w-prose leading-relaxed"
                        >
                            Layout, printing and finishing all happen under one
                            roof, so the people who set your file are the people
                            who run it. Nothing goes on the press until the
                            artwork is right and you have said yes to it.
                        </p>

                        <ul class="grid gap-4 sm:grid-cols-2">
                            <li
                                v-for="point in PROOF_POINTS"
                                :key="point.title"
                                class="flex gap-3"
                            >
                                <span
                                    class="bg-primary/10 text-primary flex size-9 shrink-0 items-center justify-center rounded-lg"
                                >
                                    <component
                                        :is="point.icon"
                                        class="size-4"
                                    />
                                </span>
                                <div class="flex flex-col gap-0.5">
                                    <span class="text-sm font-semibold">
                                        {{ point.title }}
                                    </span>
                                    <span
                                        class="text-muted-foreground text-sm leading-relaxed"
                                    >
                                        {{ point.body }}
                                    </span>
                                </div>
                            </li>
                        </ul>
                    </div>
                </div>
            </section>

            <section
                id="how-it-works"
                class="bg-muted/40 border-border/60 scroll-mt-20 border-y"
            >
                <div class="mx-auto w-full max-w-6xl px-6 py-16 lg:py-20">
                    <div class="mb-10 flex flex-col gap-2">
                        <span
                            class="text-primary text-xs font-bold tracking-[0.12em] uppercase"
                        >
                            How it works
                        </span>
                        <h2
                            class="text-2xl leading-tight font-bold tracking-tight sm:text-3xl"
                        >
                            No guesswork, no lost paper slips
                        </h2>
                        <p class="text-muted-foreground max-w-prose">
                            Here's what happens between handing us your file and
                            walking out with the print.
                        </p>
                    </div>

                    <ol class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                        <li
                            v-for="(step, index) in PROCESS"
                            :key="step.title"
                            class="bg-card border-border relative flex flex-col gap-3 overflow-hidden rounded-2xl border p-6 shadow-sm"
                        >
                            <span
                                aria-hidden="true"
                                class="text-primary/10 absolute -top-2 right-3 text-6xl font-black tabular-nums"
                            >
                                {{ index + 1 }}
                            </span>
                            <span
                                class="bg-primary/10 text-primary flex size-10 items-center justify-center rounded-xl"
                            >
                                <component :is="step.icon" class="size-5" />
                            </span>
                            <h3 class="relative font-semibold">
                                {{ step.title }}
                            </h3>
                            <p
                                class="text-muted-foreground relative text-sm leading-relaxed"
                            >
                                {{ step.body }}
                            </p>
                        </li>
                    </ol>
                </div>
            </section>
        </main>

        <!-- Closing band and footer share one dark surface. `dark` is scoped
             here (the same trick the queue display uses) so the band looks
             identical in both themes — and so the Squarefoot logo, whose
             "Graphics & Ads" line is baked in white, always has something
             dark to sit on. -->
        <div class="dark bg-background text-foreground">
            <section class="border-border/60 border-b">
                <div
                    class="mx-auto flex w-full max-w-6xl flex-col items-center gap-6 px-6 py-16 text-center"
                >
                    <img
                        src="/business_logo.png"
                        alt="Squarefoot Graphics &amp; Ads"
                        class="h-auto w-56 object-contain"
                    />
                    <h2
                        class="max-w-2xl text-2xl leading-tight font-bold tracking-tight text-balance sm:text-3xl"
                    >
                        Drop by the shop, or check an order you already have
                        with us.
                    </h2>
                    <p class="text-muted-foreground max-w-prose">
                        Order tracking is open to everyone — no account needed,
                        just the number on your slip.
                    </p>
                    <div
                        class="flex flex-wrap items-center justify-center gap-3"
                    >
                        <Button as-child size="lg">
                            <a href="#track">
                                Track an order
                                <ArrowRight class="size-4" />
                            </a>
                        </Button>
                        <Button as-child size="lg" variant="outline">
                            <a href="#what-we-print">See what we print</a>
                        </Button>
                    </div>
                </div>
            </section>

            <footer>
                <div
                    class="text-muted-foreground mx-auto flex w-full max-w-6xl flex-col gap-4 px-6 py-8 text-sm sm:flex-row sm:items-center sm:justify-between"
                >
                    <p>
                        Inkspire — the order management system for Squarefoot
                        Graphics &amp; Ads.
                    </p>
                    <nav class="flex flex-wrap items-center gap-x-6 gap-y-2">
                        <a
                            href="#what-we-print"
                            class="hover:text-foreground transition-colors"
                        >
                            What we print
                        </a>
                        <a
                            href="#how-it-works"
                            class="hover:text-foreground transition-colors"
                        >
                            How it works
                        </a>
                        <a
                            href="#track"
                            class="hover:text-foreground transition-colors"
                        >
                            Track an order
                        </a>
                    </nav>
                </div>
            </footer>
        </div>
    </div>
</template>

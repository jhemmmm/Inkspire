<script setup lang="ts">
import { Head, usePoll } from '@inertiajs/vue3';
import { Banknote, PackageCheck, PencilRuler, Ticket } from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import EmptyState from '@/components/EmptyState.vue';
import PublicPage from '@/components/PublicPage.vue';
import { queueNumberLabel } from '@/lib/utils';

/**
 * The shop's wall board: today's queue numbers, and where each one should
 * go. QueueDisplayController decides the stations from what each visit's job
 * orders need; this page only names them.
 */

/** What each station is for, in the customer's words. */
const STATIONS = {
    artist: {
        icon: PencilRuler,
        purpose: 'for your design',
        tone: 'bg-primary/10 text-primary',
    },
    cashier: {
        icon: Banknote,
        purpose: 'to pay',
        tone: 'bg-warning/15 text-warning',
    },
    frontline: {
        icon: PackageCheck,
        purpose: 'to claim your order',
        tone: 'bg-success/15 text-success',
    },
} as const;

interface Station {
    to: keyof typeof STATIONS;
    /** "Artist 2", "Cashier", "Frontline". */
    label: string;
}

interface QueueEntryRecord {
    id: number;
    queue_prefix: string;
    queue_number: number;
    /** Empty while the number is still waiting for an artist. */
    stations: Station[];
}

const props = defineProps<{
    queueEntries: QueueEntryRecord[];
}>();

// D-10: client-side polling only — no Laravel Echo/Reverb/websockets.
usePoll(5000, { only: ['queueEntries'] });

// Presentational-only clock, entirely separate from the data-polling
// mechanism above. Empty until mounted: the server's second and the
// browser's never match, which fails hydration.
const now = ref<Date | null>(null);
let clockTimer: ReturnType<typeof setInterval> | undefined;

onMounted(() => {
    now.value = new Date();
    clockTimer = setInterval(() => {
        now.value = new Date();
    }, 1000);
});

onBeforeUnmount(() => {
    clearInterval(clockTimer);
});

const currentTime = computed(() =>
    now.value?.toLocaleTimeString('en-PH', {
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
    }),
);

const called = computed(() =>
    props.queueEntries.filter((entry) => entry.stations.length > 0),
);

const waiting = computed(() =>
    props.queueEntries.filter((entry) => entry.stations.length === 0),
);
</script>

<template>
    <Head title="Queue Display" />

    <PublicPage width="max-w-6xl">
        <template #aside>
            <p
                class="text-muted-foreground text-lg font-semibold tabular-nums sm:text-xl"
            >
                {{ currentTime }}
            </p>
        </template>

        <div class="flex flex-col gap-8">
            <div>
                <div class="bg-primary mb-4 h-[3px] w-10 rounded-full" />
                <h1
                    class="text-2xl leading-tight font-extrabold tracking-tight sm:text-4xl"
                >
                    Queue
                </h1>
                <p class="text-muted-foreground mt-1 sm:text-lg">
                    Watch for your number and go where it says. Rush numbers
                    start with R and are called first.
                </p>
            </div>

            <div
                v-if="queueEntries.length === 0"
                class="bg-card border-border rounded-2xl border py-10"
            >
                <EmptyState
                    :icon="Ticket"
                    title="No numbers are waiting right now"
                    description="New queue numbers appear here on their own."
                    class="mx-auto"
                />
            </div>

            <section v-if="called.length > 0" class="flex flex-col gap-4">
                <h2
                    class="text-muted-foreground text-sm font-bold tracking-widest uppercase"
                >
                    Please proceed to
                </h2>
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <article
                        v-for="entry in called"
                        :key="entry.id"
                        class="bg-card border-border flex flex-col gap-4 rounded-2xl border p-5 shadow-[0_1px_2px_rgba(0,0,0,0.04),0_10px_30px_rgba(0,40,142,0.07)]"
                    >
                        <p
                            class="text-primary text-5xl leading-none font-extrabold tracking-tight whitespace-nowrap tabular-nums sm:text-[56px]"
                        >
                            {{
                                queueNumberLabel(
                                    entry.queue_prefix,
                                    entry.queue_number,
                                )
                            }}
                        </p>
                        <ul
                            class="border-border flex flex-col gap-3 border-t pt-4"
                        >
                            <li
                                v-for="station in entry.stations"
                                :key="station.to + station.label"
                                class="flex items-center gap-3"
                            >
                                <span
                                    :class="[
                                        STATIONS[station.to].tone,
                                        'flex size-11 shrink-0 items-center justify-center rounded-xl',
                                    ]"
                                >
                                    <component
                                        :is="STATIONS[station.to].icon"
                                        class="size-5"
                                    />
                                </span>
                                <span class="flex min-w-0 flex-col">
                                    <span
                                        class="text-xl leading-tight font-extrabold tracking-tight"
                                    >
                                        {{ station.label }}
                                    </span>
                                    <span class="text-muted-foreground text-sm">
                                        {{ STATIONS[station.to].purpose }}
                                    </span>
                                </span>
                            </li>
                        </ul>
                    </article>
                </div>
            </section>

            <section v-if="waiting.length > 0" class="flex flex-col gap-4">
                <h2
                    class="text-muted-foreground text-sm font-bold tracking-widest uppercase"
                >
                    Waiting for an artist
                </h2>
                <ul
                    class="grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-6"
                >
                    <li
                        v-for="entry in waiting"
                        :key="entry.id"
                        class="bg-card border-border rounded-xl border px-2 py-4 text-center text-3xl leading-none font-extrabold tracking-tight whitespace-nowrap tabular-nums"
                    >
                        {{
                            queueNumberLabel(
                                entry.queue_prefix,
                                entry.queue_number,
                            )
                        }}
                    </li>
                </ul>
            </section>
        </div>
    </PublicPage>
</template>

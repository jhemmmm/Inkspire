<script setup lang="ts">
import { Head, usePoll } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import { queueNumberLabel } from '@/lib/utils';

interface QueueEntryRecord {
    id: number;
    queue_prefix: string;
    queue_number: number;
    status: 'waiting' | 'serving' | 'done';
}

const props = defineProps<{
    queueEntries: QueueEntryRecord[];
}>();

// D-10: client-side polling only — no Laravel Echo/Reverb/websockets.
usePoll(5000, { only: ['queueEntries'] });

// Presentational-only clock, entirely separate from the data-polling
// mechanism above.
const now = ref(new Date());
let clockTimer: ReturnType<typeof setInterval> | undefined;

onMounted(() => {
    clockTimer = setInterval(() => {
        now.value = new Date();
    }, 1000);
});

onBeforeUnmount(() => {
    clearInterval(clockTimer);
});

const currentTime = computed(() =>
    now.value.toLocaleTimeString('en-PH', {
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
    }),
);

const waiting = computed(() =>
    props.queueEntries.filter((entry) => entry.status === 'waiting'),
);
const serving = computed(() =>
    props.queueEntries.filter((entry) => entry.status === 'serving'),
);
const done = computed(() =>
    props.queueEntries.filter((entry) => entry.status === 'done'),
);
</script>

<template>
    <Head title="Queue Display" />

    <div class="dark bg-background text-foreground min-h-screen">
        <div class="mx-auto flex max-w-6xl flex-col gap-8 p-8">
            <header class="flex items-center justify-between">
                <h1 class="text-3xl font-semibold">Queue Display</h1>
                <p
                    class="text-muted-foreground text-xl font-semibold tabular-nums"
                >
                    {{ currentTime }}
                </p>
            </header>

            <div
                v-if="queueEntries.length === 0"
                class="flex flex-col items-center gap-2 py-24 text-center"
            >
                <p class="text-2xl font-semibold">Queue is empty</p>
                <p class="text-muted-foreground">
                    New queue numbers will appear here automatically.
                </p>
            </div>

            <template v-else>
                <section v-if="serving.length > 0" class="flex flex-col gap-4">
                    <h2 class="text-muted-foreground text-lg font-semibold">
                        Serving
                    </h2>
                    <div
                        class="grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-4"
                    >
                        <Card v-for="entry in serving" :key="entry.id">
                            <CardContent
                                class="flex flex-col items-center gap-3 py-6"
                            >
                                <p
                                    class="text-[64px] leading-[1.1] font-semibold"
                                >
                                    {{
                                        queueNumberLabel(
                                            entry.queue_prefix,
                                            entry.queue_number,
                                        )
                                    }}
                                </p>
                                <Badge
                                    variant="default"
                                    class="text-base font-semibold"
                                >
                                    Serving
                                </Badge>
                            </CardContent>
                        </Card>
                    </div>
                </section>

                <section v-if="waiting.length > 0" class="flex flex-col gap-4">
                    <h2 class="text-muted-foreground text-lg font-semibold">
                        Waiting
                    </h2>
                    <div
                        class="grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-4"
                    >
                        <Card v-for="entry in waiting" :key="entry.id">
                            <CardContent
                                class="flex flex-col items-center gap-3 py-6"
                            >
                                <p
                                    class="text-[64px] leading-[1.1] font-semibold"
                                >
                                    {{
                                        queueNumberLabel(
                                            entry.queue_prefix,
                                            entry.queue_number,
                                        )
                                    }}
                                </p>
                                <Badge
                                    variant="outline"
                                    class="text-base font-semibold"
                                >
                                    Waiting
                                </Badge>
                            </CardContent>
                        </Card>
                    </div>
                </section>

                <section v-if="done.length > 0" class="flex flex-col gap-4">
                    <h2 class="text-muted-foreground text-lg font-semibold">
                        Done
                    </h2>
                    <div
                        class="grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-4"
                    >
                        <Card v-for="entry in done" :key="entry.id">
                            <CardContent
                                class="flex flex-col items-center gap-3 py-6"
                            >
                                <p
                                    class="text-[64px] leading-[1.1] font-semibold"
                                >
                                    {{
                                        queueNumberLabel(
                                            entry.queue_prefix,
                                            entry.queue_number,
                                        )
                                    }}
                                </p>
                                <Badge
                                    class="text-base font-semibold text-green-600 dark:text-green-400"
                                >
                                    Done
                                </Badge>
                            </CardContent>
                        </Card>
                    </div>
                </section>
            </template>
        </div>
    </div>
</template>

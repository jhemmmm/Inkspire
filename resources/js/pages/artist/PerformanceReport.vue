<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { artistNavItems } from '@/config/nav/artist';
import { index as performanceReportIndex } from '@/routes/artist/performance-report';

interface Stats {
    jobsCompleted: number;
    avgRevisions: number;
    slaAdherence: number;
}

interface Filters {
    from?: string;
    to?: string;
}

const props = defineProps<{
    stats: Stats;
    filters: Filters;
}>();

defineOptions({
    layout: {
        navItems: artistNavItems,
        breadcrumbs: [
            {
                title: 'Performance Report',
                href: performanceReportIndex(),
            },
        ],
    },
});

const fromDate = ref(props.filters.from ?? '');
const toDate = ref(props.filters.to ?? '');

function visit(): void {
    router.get(
        performanceReportIndex.url(),
        {
            ...(fromDate.value ? { from: fromDate.value } : {}),
            ...(toDate.value ? { to: toDate.value } : {}),
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

function clearFilters(): void {
    fromDate.value = '';
    toDate.value = '';
    visit();
}
</script>

<template>
    <Head title="Performance Report" />

    <div
        class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4"
    >
        <h1 class="text-[28px] leading-[1.2] font-semibold">
            Performance Report
        </h1>

        <form class="flex flex-wrap items-end gap-4" @submit.prevent="visit()">
            <div class="flex flex-col gap-1">
                <label
                    class="text-sm font-semibold"
                    for="performance-filter-from"
                    >From</label
                >
                <Input
                    id="performance-filter-from"
                    v-model="fromDate"
                    type="date"
                    class="w-40"
                />
            </div>

            <div class="flex flex-col gap-1">
                <label class="text-sm font-semibold" for="performance-filter-to"
                    >To</label
                >
                <Input
                    id="performance-filter-to"
                    v-model="toDate"
                    type="date"
                    class="w-40"
                />
            </div>

            <Button type="submit" data-test="apply-performance-filters-button">
                Apply Filters
            </Button>
            <Button
                type="button"
                variant="secondary"
                data-test="clear-performance-filters-button"
                @click="clearFilters"
            >
                Clear
            </Button>
        </form>

        <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
            <Card>
                <CardContent class="flex flex-col gap-1">
                    <p class="text-[28px] leading-[1.2] font-semibold">
                        {{ stats.jobsCompleted }}
                    </p>
                    <p class="text-sm font-semibold">Jobs Completed</p>
                </CardContent>
            </Card>

            <Card>
                <CardContent class="flex flex-col gap-1">
                    <p class="text-[28px] leading-[1.2] font-semibold">
                        {{ stats.avgRevisions }}
                    </p>
                    <p class="text-sm font-semibold">Avg. Revisions per Job</p>
                </CardContent>
            </Card>

            <Card>
                <CardContent class="flex flex-col gap-1">
                    <p class="text-[28px] leading-[1.2] font-semibold">
                        {{ stats.slaAdherence }}%
                    </p>
                    <p class="text-sm font-semibold">SLA Adherence</p>
                </CardContent>
            </Card>
        </div>

        <div
            v-if="stats.jobsCompleted === 0"
            class="flex flex-col items-center gap-1 text-center"
        >
            <p class="font-semibold">No completed job orders in this range</p>
            <p class="text-muted-foreground">Try a wider date range.</p>
        </div>
    </div>
</template>

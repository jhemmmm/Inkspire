<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { CalendarRange, CircleCheck, Repeat2, Target } from '@lucide/vue';
import { ref } from 'vue';
import DataTableCard from '@/components/DataTableCard.vue';
import EmptyState from '@/components/EmptyState.vue';
import PageContainer from '@/components/PageContainer.vue';
import PageHeader from '@/components/PageHeader.vue';
import SectionHeading from '@/components/SectionHeading.vue';
import StatCard from '@/components/StatCard.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Table,
    TableBody,
    TableCell,
    TableEmpty,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { artistNavItems } from '@/config/nav/artist';
import { index as performanceReportIndex } from '@/routes/artist/performance-report';

interface Stats {
    jobsCompleted: number;
    avgRevisions: number;
    slaAdherence: number;
    slaDays: number;
}

interface CompletedJobOrder {
    id: number;
    number: string | null;
    description: string;
    approved_at: string;
    revisions: number;
    days_taken: number;
    within_sla: boolean;
}

interface Filters {
    from?: string;
    to?: string;
}

const props = defineProps<{
    stats: Stats;
    completedJobOrders: CompletedJobOrder[];
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

/** Matches the 'en-PH' long-date convention used across the other portals. */
function approvedLabel(approvedAt: string): string {
    return new Date(approvedAt).toLocaleDateString('en-PH', {
        month: 'long',
        day: 'numeric',
        year: 'numeric',
    });
}

function daysLabel(days: number): string {
    return days === 1 ? '1 day' : `${days} days`;
}

function clearFilters(): void {
    fromDate.value = '';
    toDate.value = '';
    visit();
}
</script>

<template>
    <Head title="Performance Report" />

    <PageContainer>
        <PageHeader
            title="Performance Report"
            description="Your throughput and revision counts over the selected period."
        />

        <Card>
            <CardHeader :icon="CalendarRange">
                <CardTitle>Date Range</CardTitle>
            </CardHeader>
            <CardContent>
                <form
                    class="grid gap-4 @md:grid-cols-2 @3xl:grid-cols-4"
                    @submit.prevent="visit()"
                >
                    <div class="flex min-w-0 flex-col gap-2">
                        <Label for="performance-filter-from">From</Label>
                        <Input
                            id="performance-filter-from"
                            v-model="fromDate"
                            type="date"
                            class="w-full"
                        />
                    </div>

                    <div class="flex min-w-0 flex-col gap-2">
                        <Label for="performance-filter-to">To</Label>
                        <Input
                            id="performance-filter-to"
                            v-model="toDate"
                            type="date"
                            class="w-full"
                        />
                    </div>

                    <div
                        class="flex flex-wrap items-center gap-2 @md:col-span-2"
                    >
                        <Button
                            type="submit"
                            data-test="apply-performance-filters-button"
                        >
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
                    </div>
                </form>
            </CardContent>
        </Card>

        <div class="grid grid-cols-1 gap-4 @lg:grid-cols-2 @3xl:grid-cols-3">
            <StatCard
                :value="stats.jobsCompleted"
                label="Jobs Completed"
                hint="Designs the client approved"
                :icon="CircleCheck"
            />

            <StatCard
                :value="stats.avgRevisions"
                label="Avg. Revisions per Job"
                hint="Rounds of changes before approval"
                :icon="Repeat2"
                ink="magenta"
            />

            <StatCard
                :value="`${stats.slaAdherence}%`"
                label="SLA Adherence"
                :hint="`Approved within ${daysLabel(stats.slaDays)} of intake`"
                :icon="Target"
                ink="key"
            />
        </div>

        <section class="flex flex-col gap-3">
            <SectionHeading
                title="Completed Job Orders"
                description="The jobs behind the figures above, most recently approved first."
            />
            <DataTableCard>
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Job Order</TableHead>
                            <TableHead>Approved</TableHead>
                            <TableHead class="text-right">Revisions</TableHead>
                            <TableHead class="text-right">Turnaround</TableHead>
                            <TableHead>SLA</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableEmpty
                            v-if="completedJobOrders.length === 0"
                            :colspan="5"
                        >
                            <EmptyState
                                title="No completed job orders in this range"
                                description="Only designs the client approved count here. Try a wider date range."
                                :icon="CalendarRange"
                            />
                        </TableEmpty>
                        <TableRow
                            v-for="jobOrder in completedJobOrders"
                            v-else
                            :key="jobOrder.id"
                            :data-test="`completed-job-order-${jobOrder.id}-row`"
                        >
                            <TableCell>
                                <div class="flex flex-col gap-1">
                                    <span>{{ jobOrder.description }}</span>
                                    <span
                                        class="text-muted-foreground text-xs tabular-nums"
                                    >
                                        {{ jobOrder.number ?? '—' }}
                                    </span>
                                </div>
                            </TableCell>
                            <TableCell class="text-muted-foreground">
                                {{ approvedLabel(jobOrder.approved_at) }}
                            </TableCell>
                            <TableCell class="text-right tabular-nums">
                                {{ jobOrder.revisions }}
                            </TableCell>
                            <TableCell class="text-right tabular-nums">
                                {{ daysLabel(jobOrder.days_taken) }}
                            </TableCell>
                            <TableCell>
                                <StatusBadge
                                    v-if="jobOrder.within_sla"
                                    tone="success"
                                >
                                    On time
                                </StatusBadge>
                                <StatusBadge v-else tone="warning">
                                    Over {{ daysLabel(stats.slaDays) }}
                                </StatusBadge>
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </DataTableCard>
        </section>
    </PageContainer>
</template>

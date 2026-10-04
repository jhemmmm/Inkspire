<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    Activity,
    ArrowRight,
    ChartColumn,
    CreditCard,
    FileMinus,
    Factory,
    Layers,
    Lock,
    Ticket,
    Unlock,
    UserCheck,
    Wallet,
} from '@lucide/vue';
import { computed } from 'vue';
import BarChart from '@/components/BarChart.vue';
import BreakdownChart from '@/components/BreakdownChart.vue';
import DataTableCard from '@/components/DataTableCard.vue';
import EmptyState from '@/components/EmptyState.vue';
import PageContainer from '@/components/PageContainer.vue';
import PageHeader from '@/components/PageHeader.vue';
import SectionHeading from '@/components/SectionHeading.vue';
import StatCard from '@/components/StatCard.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableEmpty,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useLivePoll } from '@/composables/useLivePoll';
import { adminNavItems } from '@/config/nav/admin';
import type { ChartSeries } from '@/lib/charts';
import { jobOrderStatusLabel, money } from '@/lib/jobOrders';
import { dashboard } from '@/routes/admin';
import { index as auditTrailIndex } from '@/routes/admin/audit-trail';
import { index as creditRequestsIndex } from '@/routes/admin/credit-requests';
import { index as designOverridesIndex } from '@/routes/admin/design-overrides';
import { index as reportsIndex } from '@/routes/admin/reports';
import { index as usersIndex } from '@/routes/admin/users';
import { index as writeOffRequestsIndex } from '@/routes/admin/write-off-requests';

const props = defineProps<{
    attention: {
        creditRequests: number;
        writeOffRequests: number;
        lockedDesigns: number;
        lockedAccounts: number;
    };
    shop: {
        queuedToday: number;
        inProduction: number;
        unpaidJobOrders: number;
        outstandingAmount: number;
        activeStaff: number;
        totalStaff: number;
    };
    cashFlow: { labels: string[]; series: ChartSeries[] };
    /** Open job orders per status, in workflow order. */
    pipeline: Record<string, number>;
    recentActivity: {
        id: number;
        action: string;
        entity: string;
        user: string | null;
        created_at: string | null;
    }[];
}>();

// Everything except the 14-day chart, which only moves with completed days
// and would redraw on every poll.
useLivePoll(['attention', 'shop', 'pipeline', 'recentActivity']);

defineOptions({
    layout: {
        navItems: adminNavItems,
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: dashboard(),
            },
        ],
    },
});

/**
 * The four decisions only an Admin can make. Each tile links to the page
 * that makes it, and turns `attention` the moment it is non-zero — these
 * are queues where waiting has a cost to somebody else.
 */
const attentionTiles = computed(() => [
    {
        key: 'credit',
        label: 'Credit Requests',
        value: props.attention.creditRequests,
        hint: 'A cashier cannot take payment on credit until you decide.',
        icon: CreditCard,
        href: creditRequestsIndex(),
    },
    {
        key: 'write-off',
        label: 'Write-Off Requests',
        value: props.attention.writeOffRequests,
        hint: 'Accounting has asked to write these receivables off.',
        icon: FileMinus,
        href: writeOffRequestsIndex(),
    },
    {
        key: 'designs',
        label: 'Locked Designs',
        value: props.attention.lockedDesigns,
        hint: 'An artist cannot edit these again until you unlock them.',
        icon: Unlock,
        href: designOverridesIndex(),
    },
    {
        key: 'accounts',
        label: 'Locked-Out Accounts',
        value: props.attention.lockedAccounts,
        hint: 'Staff currently shut out by failed sign-ins.',
        icon: Lock,
        href: usersIndex({ query: { status: 'locked' } }),
    },
]);

/** Only the stages that hold work, still in workflow order. */
const pipelineItems = computed(() =>
    Object.entries(props.pipeline)
        .filter(([, count]) => count > 0)
        .map(([status, count]) => ({
            label: jobOrderStatusLabel(status),
            value: count,
        })),
);

const nothingWaiting = computed(() =>
    attentionTiles.value.every((tile) => tile.value === 0),
);

function dateTime(iso: string | null): string {
    if (iso === null) {
        return '—';
    }

    return new Date(iso).toLocaleString('en-PH', {
        month: 'short',
        day: '2-digit',
        hour: 'numeric',
        minute: '2-digit',
    });
}

const ACTION_LABELS: Record<string, string> = {
    created: 'Created',
    updated: 'Updated',
    deleted: 'Deleted',
    login: 'Signed in',
    logout: 'Signed out',
    failed_login: 'Failed sign-in',
    lockout: 'Locked out',
};

function actionLabel(action: string): string {
    return ACTION_LABELS[action] ?? action.replaceAll('_', ' ');
}
</script>

<template>
    <Head title="Admin Dashboard" />

    <PageContainer>
        <PageHeader
            title="Admin Dashboard"
            description="What is waiting on your decision, and how the shop is running today."
        />

        <SectionHeading
            title="Needs Your Decision"
            description="Nobody else can clear these. Each tile opens the queue behind it."
        />

        <p
            v-if="nothingWaiting"
            class="text-muted-foreground text-sm"
            data-test="admin-nothing-waiting"
        >
            Nothing is waiting on you right now.
        </p>

        <div class="grid grid-cols-1 gap-4 @lg:grid-cols-2 @5xl:grid-cols-4">
            <Link
                v-for="tile in attentionTiles"
                :key="tile.key"
                :href="tile.href"
                class="focus-visible:ring-ring rounded-xl focus-visible:ring-[3px] focus-visible:outline-none"
                :data-test="`admin-attention-${tile.key}`"
            >
                <StatCard
                    :label="tile.label"
                    :value="tile.value"
                    :hint="tile.hint"
                    :icon="tile.icon"
                    :tone="tile.value > 0 ? 'attention' : 'default'"
                    ink="magenta"
                />
            </Link>
        </div>

        <SectionHeading
            title="The Shop Today"
            description="Live counts, derived on read — nothing here is stored or needs syncing."
        />

        <div class="grid grid-cols-1 gap-4 @lg:grid-cols-2 @5xl:grid-cols-4">
            <StatCard
                label="Queued Today"
                :value="shop.queuedToday"
                hint="Visits on today's business date, both lanes."
                :icon="Ticket"
            />
            <StatCard
                label="In Production"
                :value="shop.inProduction"
                hint="Waiting for the press or printing."
                :icon="Factory"
            />
            <StatCard
                label="Outstanding"
                :value="money(shop.outstandingAmount)"
                :hint="`Still owed across ${shop.unpaidJobOrders} job order(s).`"
                :icon="Wallet"
                ink="yellow"
            />
            <StatCard
                label="Active Staff"
                :value="`${shop.activeStaff} / ${shop.totalStaff}`"
                hint="Accounts that can sign in today."
                :icon="UserCheck"
                ink="magenta"
            />
        </div>

        <div class="grid gap-4 @3xl:grid-cols-2">
            <Card>
                <CardHeader :icon="ChartColumn">
                    <div class="flex items-center justify-between gap-2">
                        <div class="flex flex-col gap-1">
                            <CardTitle>Revenue and Expenses</CardTitle>
                            <CardDescription>
                                The last 14 days, by the day money arrived or
                                was spent.
                            </CardDescription>
                        </div>
                        <Button as-child variant="outline" size="sm">
                            <Link
                                :href="
                                    reportsIndex({
                                        query: { report: 'financial-summary' },
                                    })
                                "
                            >
                                Report
                                <ArrowRight class="size-4" />
                            </Link>
                        </Button>
                    </div>
                </CardHeader>
                <CardContent>
                    <BarChart
                        label="Revenue and expenses, last 14 days"
                        :labels="cashFlow.labels"
                        :series="cashFlow.series"
                        format="money"
                        empty-text="No money in or out in the last 14 days."
                    />
                </CardContent>
            </Card>
            <Card>
                <CardHeader :icon="Layers">
                    <CardTitle>Open Job Orders by Stage</CardTitle>
                    <CardDescription>
                        Where the work is sitting right now, from intake to
                        pickup.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <BreakdownChart
                        label="Open job orders by stage"
                        :items="pipelineItems"
                        empty-text="No open job orders."
                    />
                </CardContent>
            </Card>
        </div>

        <div class="flex flex-wrap items-end justify-between gap-4">
            <SectionHeading
                title="Recent Activity"
                description="The last few entries in the audit trail, across every portal."
            />
            <Button as-child variant="outline" size="sm">
                <Link :href="auditTrailIndex()">
                    Full audit trail
                    <ArrowRight class="size-4" />
                </Link>
            </Button>
        </div>

        <DataTableCard>
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Action</TableHead>
                        <TableHead>Record</TableHead>
                        <TableHead>By</TableHead>
                        <TableHead>When</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableEmpty v-if="recentActivity.length === 0" :colspan="4">
                        <EmptyState
                            :icon="Activity"
                            title="No activity recorded yet"
                            description="Entries appear here as staff sign in and work job orders."
                        />
                    </TableEmpty>
                    <TableRow
                        v-for="entry in recentActivity"
                        v-else
                        :key="entry.id"
                    >
                        <TableCell class="font-medium">
                            {{ actionLabel(entry.action) }}
                        </TableCell>
                        <TableCell class="text-muted-foreground">
                            {{ entry.entity || '—' }}
                        </TableCell>
                        <TableCell>{{ entry.user ?? 'System' }}</TableCell>
                        <TableCell class="text-muted-foreground tabular-nums">
                            {{ dateTime(entry.created_at) }}
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </DataTableCard>
    </PageContainer>
</template>

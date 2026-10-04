<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import ReportsWorkspace from '@/components/reports/ReportsWorkspace.vue';
import PageContainer from '@/components/PageContainer.vue';
import PageHeader from '@/components/PageHeader.vue';
import type { ReportChart } from '@/lib/charts';
import { adminNavItems } from '@/config/nav/admin';
import {
    pdf as reportsExportPdf,
    xlsx as reportsExportXlsx,
} from '@/routes/admin/reports/export';
import { index as reportsIndex } from '@/routes/admin/reports';

interface ReportDefinition {
    title: string;
    subLine: string;
    badge: string;
    columns: string[];
}

interface FinancialSummary {
    job_sales: number;
    cancellation_fees: number;
    revenue_total: number;
    expenses_total: number;
    result: number;
    write_off_total: number;
}

const props = defineProps<{
    reports: Record<string, ReportDefinition>;
    selected: string;
    columns: string[];
    rows: Record<string, unknown>[];
    rowsTotal: number;
    rowsAmountTotal: number | null;
    summary: FinancialSummary | null;
    chart: ReportChart;
    filters: { from: string; to: string; q: string };
}>();

defineOptions({
    layout: {
        navItems: adminNavItems,
        breadcrumbs: [{ title: 'Reports', href: reportsIndex() }],
    },
});

function exportPdfUrl(key: string): string {
    return reportsExportPdf.url(key, {
        query: {
            from: props.filters.from,
            to: props.filters.to,
            q: props.filters.q || undefined,
        },
    });
}

function exportXlsxUrl(key: string): string {
    return reportsExportXlsx.url(key, {
        query: {
            from: props.filters.from,
            to: props.filters.to,
            q: props.filters.q || undefined,
        },
    });
}
</script>

<template>
    <Head title="Reports" />

    <PageContainer>
        <PageHeader
            title="Reports"
            description="Every report in the system, over any date range you choose. Export to PDF or Excel."
        />

        <ReportsWorkspace
            v-bind="props"
            :index-url="reportsIndex.url()"
            :export-pdf-url="exportPdfUrl"
            :export-xlsx-url="exportXlsxUrl"
        />
    </PageContainer>
</template>

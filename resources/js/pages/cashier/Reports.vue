<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import ReportsWorkspace from '@/components/reports/ReportsWorkspace.vue';
import PageContainer from '@/components/PageContainer.vue';
import PageHeader from '@/components/PageHeader.vue';
import { cashierNavItems } from '@/config/nav/cashier';
import {
    pdf as reportsExportPdf,
    xlsx as reportsExportXlsx,
} from '@/routes/cashier/reports/export';
import { index as reportsIndex } from '@/routes/cashier/reports';

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
    filters: { from: string; to: string };
}>();

defineOptions({
    layout: {
        navItems: cashierNavItems,
        breadcrumbs: [{ title: 'Reports', href: reportsIndex() }],
    },
});

function exportPdfUrl(key: string): string {
    return reportsExportPdf.url(key, {
        query: { from: props.filters.from, to: props.filters.to },
    });
}

function exportXlsxUrl(key: string): string {
    return reportsExportXlsx.url(key, {
        query: { from: props.filters.from, to: props.filters.to },
    });
}
</script>

<template>
    <Head title="Reports" />

    <PageContainer>
        <PageHeader
            title="Reports"
            description="Sales and cancellations at the counter, over any date range you choose. Export to PDF or Excel."
        />

        <ReportsWorkspace
            v-bind="props"
            :index-url="reportsIndex.url()"
            :export-pdf-url="exportPdfUrl"
            :export-xlsx-url="exportXlsxUrl"
        />
    </PageContainer>
</template>

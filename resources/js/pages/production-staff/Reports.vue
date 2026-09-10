<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import ReportsWorkspace from '@/components/reports/ReportsWorkspace.vue';
import { productionStaffNavItems } from '@/config/nav/production-staff';
import {
    pdf as reportsExportPdf,
    xlsx as reportsExportXlsx,
} from '@/routes/production-staff/reports/export';
import { index as reportsIndex } from '@/routes/production-staff/reports';

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
    summary: FinancialSummary | null;
    filters: { from: string; to: string };
}>();

defineOptions({
    layout: {
        navItems: productionStaffNavItems,
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

    <div
        class="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4"
    >
        <div class="flex flex-col gap-1">
            <h1 class="text-[28px] leading-[1.2] font-semibold">Reports</h1>
            <p class="text-muted-foreground text-sm">
                Where every job order stands in production, over any date range
                you choose.
            </p>
        </div>

        <ReportsWorkspace
            v-bind="props"
            :index-url="reportsIndex.url()"
            :export-pdf-url="exportPdfUrl"
            :export-xlsx-url="exportXlsxUrl"
        />
    </div>
</template>

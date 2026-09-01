<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { dashboard } from '@/routes/accounting-staff';

defineOptions({
    layout: {
        navItems: [],
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: dashboard(),
            },
        ],
    },
});
</script>

<template>
    <Head title="Accounting Staff Dashboard" />

    <div
        class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4"
    >
        <h1 class="text-[28px] leading-[1.2] font-semibold">
            Accounting Staff Dashboard
        </h1>
        <p class="text-muted-foreground">
            There's nothing here yet — your tools will appear in a later
            phase.
        </p>
    </div>
</template>

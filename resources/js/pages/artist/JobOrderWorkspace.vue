<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

defineOptions({
    layout: {
        navItems: [],
        breadcrumbs: [],
    },
});

defineProps<{
    jobOrder: {
        id: number;
        description: string;
        status: string;
        consultation_notes: string | null;
        canEditConsultation: boolean;
    };
}>();
</script>

<template>
    <Head title="Job Order Workspace" />

    <div
        class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4"
    >
        <h1 class="text-[28px] leading-[1.2] font-semibold">
            Job Order Workspace
        </h1>
        <p class="text-muted-foreground">
            There's nothing here yet — the full workspace UI arrives in a
            later plan.
        </p>
    </div>
</template>

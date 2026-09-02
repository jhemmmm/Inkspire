<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ownerNavItems } from '@/config/nav/owner';
import { dashboard } from '@/routes/owner';

interface DesignOverrideJobOrder {
    id: number;
    description: string;
}

defineProps<{
    jobOrders: DesignOverrideJobOrder[];
}>();

defineOptions({
    layout: {
        navItems: ownerNavItems,
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: dashboard(),
            },
            {
                title: 'Design Overrides',
                href: dashboard(),
            },
        ],
    },
});
</script>

<template>
    <Head title="Design Overrides" />

    <div
        class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4"
    >
        <h1 class="text-[28px] leading-[1.2] font-semibold">
            Design Overrides
        </h1>
        <p class="text-muted-foreground">
            The Unlock Design action is coming in a later phase.
        </p>
    </div>
</template>

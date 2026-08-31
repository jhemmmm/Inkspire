<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import TextLink from '@/components/TextLink.vue';
import { dashboard } from '@/routes';

defineOptions({
    layout: {
        title: 'Access denied',
        description: '',
    },
});

const props = defineProps<{
    role?: string;
}>();
</script>

<template>
    <Head title="Access denied" />

    <div class="space-y-6">
        <p class="text-muted-foreground text-center text-sm">
            You don't have access to this page. This area is only available
            to {{ props.role ?? 'that role' }} accounts. Return to your
            dashboard.
        </p>

        <div class="text-center text-sm">
            <TextLink :href="dashboard()">Return to your dashboard</TextLink>
        </div>
    </div>
</template>

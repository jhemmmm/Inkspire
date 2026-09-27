<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import TextLink from '@/components/TextLink.vue';
import { authMutedClass } from '@/layouts/auth/fields';
import { roleLabel } from '@/lib/roles';

defineOptions({
    layout: {
        title: 'Access denied',
        description: '',
    },
});

const props = defineProps<{
    role?: string;
    dashboardHref: string;
}>();
</script>

<template>
    <Head title="Access denied" />

    <div class="space-y-6">
        <p :class="[authMutedClass, 'text-center text-sm']">
            <template v-if="props.role">
                You're signed in as {{ roleLabel(props.role) }}, which doesn't
                have access to this page.
            </template>
            <template v-else> You don't have access to this page. </template>
        </p>

        <div class="text-center text-sm">
            <TextLink :href="props.dashboardHref"
                >Return to your dashboard</TextLink
            >
        </div>
    </div>
</template>

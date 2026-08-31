<script setup lang="ts">
import AppLayout from '@/layouts/app/AppSidebarLayout.vue';
import type { BreadcrumbItem, NavItem } from '@/types';

const { breadcrumbs = [], navItems } = defineProps<{
    breadcrumbs?: BreadcrumbItem[];
    navItems?: NavItem[];
}>();
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs" :nav-items="navItems">
        <slot />
    </AppLayout>
</template>

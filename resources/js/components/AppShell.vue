<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { SidebarProvider } from '@/components/ui/sidebar';
import type { AppVariant } from '@/types';

type Props = {
    variant?: AppVariant;
};

withDefaults(defineProps<Props>(), {
    variant: 'sidebar',
});

const isOpen = usePage().props.sidebarOpen;
</script>

<template>
    <div v-if="variant === 'header'" class="flex min-h-screen w-full flex-col">
        <slot />
    </div>
    <!--
        The inset variant paints the navy sidebar colour behind the whole
        page; drop it in print so receipts and letters don't come out on a
        navy sheet when "background graphics" is on.
    -->
    <SidebarProvider
        v-else
        :default-open="isOpen"
        class="print:has-data-[variant=inset]:bg-transparent"
    >
        <slot />
    </SidebarProvider>
</template>

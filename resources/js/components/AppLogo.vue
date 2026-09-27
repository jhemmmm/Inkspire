<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { roleLabel } from '@/lib/roles';

const page = usePage();
const role = computed(() => page.props.auth.user.role);
</script>

<template>
    <!--
        The wordmark goes white on the navy sidebar in both themes; anywhere
        else (the header layout) it keeps its colours until dark mode.
    -->
    <div class="flex min-w-0 items-center gap-2">
        <img
            src="/logo.png"
            alt="Inkspire"
            class="h-7 w-auto shrink-0 object-contain object-left in-data-[sidebar=sidebar]:brightness-0 in-data-[sidebar=sidebar]:invert dark:brightness-0 dark:invert"
        />
        <span
            class="bg-accent text-accent-foreground in-data-[sidebar=sidebar]:bg-sidebar-accent in-data-[sidebar=sidebar]:text-sidebar-accent-foreground in-data-[sidebar=sidebar]:ring-sidebar-foreground/20 truncate rounded-full px-2 py-0.5 text-[10px] font-bold tracking-[0.08em] uppercase ring-1 ring-transparent ring-inset group-data-[collapsible=icon]:hidden"
        >
            {{ roleLabel(role) }}
        </span>
    </div>
</template>

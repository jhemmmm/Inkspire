<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { roleLabel } from '@/lib/roles';

const page = usePage();
const role = computed(() => page.props.auth.user.role);
</script>

<template>
    <div class="flex min-w-0 items-center gap-2">
        <img
            src="/logo.png"
            alt="Inkspire"
            class="h-7 w-auto shrink-0 object-contain object-left dark:brightness-0 dark:invert"
        />
        <span
            class="bg-accent text-accent-foreground truncate rounded-full px-2 py-0.5 text-[10px] font-bold tracking-[0.08em] uppercase group-data-[collapsible=icon]:hidden"
        >
            {{ roleLabel(role) }}
        </span>
    </div>
</template>

<script setup lang="ts">
import { Moon, Sun } from '@lucide/vue';
import { useAppearance } from '@/composables/useAppearance';

const { resolvedAppearance, updateAppearance } = useAppearance();

/**
 * A plain two-state flip. The three-way light/dark/system picker still lives
 * in Settings > Appearance for anyone who wants to follow the OS.
 */
function toggle(): void {
    updateAppearance(resolvedAppearance.value === 'dark' ? 'light' : 'dark');
}
</script>

<template>
    <button
        type="button"
        class="text-muted-foreground hover:bg-accent hover:text-accent-foreground focus-visible:ring-ring flex size-9 items-center justify-center rounded-full transition-colors focus-visible:ring-2 focus-visible:outline-none"
        :aria-label="
            resolvedAppearance === 'dark'
                ? 'Switch to light theme'
                : 'Switch to dark theme'
        "
        data-test="theme-toggle"
        @click="toggle"
    >
        <Moon v-if="resolvedAppearance === 'dark'" class="size-[18px]" />
        <Sun v-else class="size-[18px]" />
    </button>
</template>

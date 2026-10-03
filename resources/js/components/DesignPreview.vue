<script setup lang="ts">
import { Maximize2 } from '@lucide/vue';

defineProps<{
    src: string;
    alt: string;
    /**
     * Where "Open full size" goes, when not `src` itself. A stored design's
     * `src` is a signed URL that expires minutes after the page loads, so
     * its link needs a route that signs a fresh one on each click.
     */
    href?: string;
}>();
</script>

<template>
    <!--
        A capped thumbnail rather than the design at full width: a tall
        poster ran several screens down the page and pushed the actions
        under it out of reach. The full-size file opens in a new tab, where
        the browser's own zoom works.
    -->
    <a
        :href="href ?? src"
        target="_blank"
        rel="noopener"
        class="group flex w-fit max-w-full flex-col items-start gap-2"
    >
        <img
            :src="src"
            :alt="alt"
            class="bg-muted max-h-72 max-w-full rounded-lg border object-contain"
        />
        <span
            class="text-primary inline-flex items-center gap-1.5 text-sm font-medium underline-offset-4 group-hover:underline"
        >
            <Maximize2 class="size-4" />
            Open full size
        </span>
    </a>
</template>

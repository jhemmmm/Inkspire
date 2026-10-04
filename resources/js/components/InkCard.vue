<script setup lang="ts">
/**
 * The homepage's white panel, for the customer pages: the soft royal-blue
 * lift, and the shop's four process inks along the top edge.
 *
 * `overflow-clip`, not `overflow-hidden`: hidden would make the card a
 * scroll container, and an action bar inside it could no longer stick to
 * the bottom of the screen.
 */
</script>

<template>
    <section
        class="bg-card relative overflow-clip rounded-[20px] shadow-[0_20px_60px_rgba(0,40,142,0.12),0_4px_16px_rgba(0,0,0,0.08)]"
    >
        <div
            aria-hidden="true"
            class="absolute inset-x-0 top-0 grid h-1 grid-cols-4"
        >
            <span class="bg-ink-cyan" />
            <span class="bg-ink-magenta" />
            <span class="bg-ink-yellow" />
            <span class="bg-ink-key" />
        </div>
        <slot />
    </section>
</template>

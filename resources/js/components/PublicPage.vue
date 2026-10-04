<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { home } from '@/routes';

/**
 * The frame every customer page shares with the homepage: the soft blue
 * ground, the logo linking home, one centred column and the shop's name at
 * the foot.
 *
 * `width` is the column's max-width utility, so the header and the content
 * stay on the same edges. `#aside` sits opposite the logo. `#after` is a
 * full-width sibling of the column, for a sticky action bar that has to run
 * from one edge of the screen to the other.
 */
defineProps<{
    width: 'max-w-xl' | 'max-w-2xl' | 'max-w-3xl' | 'max-w-6xl';
}>();
</script>

<template>
    <div
        class="bg-muted text-foreground flex min-h-screen flex-col overflow-x-clip"
    >
        <header
            :class="[
                width,
                'mx-auto flex w-full items-center justify-between gap-4 px-4 py-4 sm:px-6 sm:py-5',
            ]"
        >
            <Link :href="home()" class="rounded-md">
                <img
                    src="/logo.png"
                    alt="Inkspire home"
                    width="824"
                    height="303"
                    class="h-8 w-auto sm:h-9"
                />
            </Link>
            <slot name="aside" />
        </header>

        <main :class="[width, 'mx-auto w-full flex-1 px-4 pb-8 sm:px-6']">
            <slot />
        </main>

        <footer
            :class="[
                width,
                'text-muted-foreground mx-auto w-full px-4 pb-6 text-xs sm:px-6',
            ]"
        >
            <span class="text-foreground font-bold">
                Squarefoot Graphics &amp; Ads
            </span>
            · Job orders and tracking run on Inkspire.
        </footer>

        <slot name="after" />
    </div>
</template>

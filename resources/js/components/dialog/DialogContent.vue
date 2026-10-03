<script setup lang="ts">
import { DialogContent } from '@/components/ui/dialog';
</script>

<template>
    <!--
        The box never scrolls; the body inside it does. A dialog taller than
        the viewport stays on screen, and the close button, which the
        generated content pins to the box's corner, no longer scrolls away
        with the form.
    -->
    <DialogContent
        class="flex max-h-[calc(100dvh-2rem)] flex-col gap-0 overflow-hidden p-0"
    >
        <div class="grid min-h-0 gap-4 overflow-y-auto p-6">
            <slot />
        </div>
    </DialogContent>
</template>

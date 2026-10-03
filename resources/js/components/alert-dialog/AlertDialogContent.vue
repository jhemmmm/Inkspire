<script setup lang="ts">
import { AlertDialogContent } from '@/components/ui/alert-dialog';
</script>

<template>
    <!-- The box never scrolls; the body inside it does. See dialog/DialogContent.vue. -->
    <AlertDialogContent
        class="flex max-h-[calc(100dvh-2rem)] flex-col gap-0 overflow-hidden p-0"
    >
        <div class="grid min-h-0 gap-4 overflow-y-auto p-6">
            <slot />
        </div>
    </AlertDialogContent>
</template>

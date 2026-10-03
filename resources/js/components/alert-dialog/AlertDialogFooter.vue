<script setup lang="ts">
import { AlertDialogFooter } from '@/components/ui/alert-dialog';
</script>

<template>
    <!-- Stays in view while the body scrolls. See dialog/DialogFooter.vue. -->
    <AlertDialogFooter
        class="bg-background sticky -bottom-6 -mx-6 -mb-6 border-t px-6 py-4"
    >
        <slot />
    </AlertDialogFooter>
</template>

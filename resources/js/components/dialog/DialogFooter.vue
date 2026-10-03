<script setup lang="ts">
import { DialogFooter } from '@/components/ui/dialog';
</script>

<template>
    <!--
        Stays in view while the dialog body scrolls under it, so Save is
        never a screen away from the last field. The negative offsets undo
        the body's padding.
    -->
    <DialogFooter
        class="bg-background sticky -bottom-6 -mx-6 -mb-6 border-t px-6 py-4"
    >
        <slot />
    </DialogFooter>
</template>

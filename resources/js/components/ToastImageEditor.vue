<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from 'vue';
import ImageEditor from 'tui-image-editor';
import 'tui-color-picker/dist/tui-color-picker.css';
import 'tui-image-editor/dist/tui-image-editor.css';

const props = defineProps<{
    /** Signed temporaryUrl() for the existing design file, or null for a blank canvas (D-11). */
    initialImageUrl: string | null;
    initialImageName?: string;
}>();

const editorContainer = ref<HTMLElement | null>(null);
let editor: InstanceType<typeof ImageEditor> | null = null;

onMounted(() => {
    editor = new ImageEditor(editorContainer.value!, {
        includeUI: {
            loadImage: props.initialImageUrl
                ? {
                      path: props.initialImageUrl,
                      name: props.initialImageName ?? 'design',
                  }
                : undefined,
            theme: {},
            menu: ['crop', 'flip', 'rotate', 'draw', 'shape', 'icon', 'text', 'filter'],
            menuBarPosition: 'bottom',
        },
        cssMaxWidth: 900,
        cssMaxHeight: 600,
        usageStatistics: false,
    });
});

onBeforeUnmount(() => {
    editor?.destroy();
    editor = null;
});

/** Flattened PNG export (D-10) — returns a base64 data URI. */
function exportPng(): string {
    return editor!.toDataURL({ format: 'png' });
}

defineExpose({ exportPng });
</script>

<template>
    <div ref="editorContainer" class="tui-image-editor-wrapper" />
</template>

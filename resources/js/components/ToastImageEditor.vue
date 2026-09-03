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

const CSS_MAX_WIDTH = 900;
const CSS_MAX_HEIGHT = 600;

/**
 * tui-image-editor reuses the element passed into `new ImageEditor()`
 * directly as its own top-level `.tui-image-editor-container` (no nested
 * wrapper is created) and sizes it from `includeUI.uiSize`, which defaults
 * to `{ width: '100%', height: '100%' }` when not supplied. Our wrapper's
 * own parent has no explicit height, so an unset `height: 100%` collapses
 * to the library's `min-height: 300px` CSS fallback — while the inner
 * canvas is still explicitly sized to `CSS_MAX_HEIGHT` via a separate
 * mechanism (cssMaxWidth/cssMaxHeight), causing it to overflow and get
 * scroll-clipped inside the undersized outer container. `uiSize.height`
 * must reserve space for tui-image-editor's own hardcoded chrome: a 64px
 * header and a 64px bottom menu bar (see tui-image-editor.css
 * `.tui-image-editor-main { top: 64px }` and
 * `.tui-image-editor-controls { height: 64px }`), on top of the canvas's
 * own height.
 */
const EDITOR_CHROME_HEIGHT = 128;

/**
 * tui-image-editor only calls its internal `resizeEditor()` — the sole
 * method that sizes the editor's canvas container — from inside the
 * promise callback of loading a real image, gated behind a truthy
 * `loadImage.path`. There is no library-native "blank canvas" mode, so
 * an empty path (falsy) leaves the canvas container unsized (present in
 * the DOM but invisible/unusable). Synthesize a real white blank image
 * client-side, matching the editor's own configured dimensions, so the
 * normal image-load path (and therefore resizeEditor()) always runs.
 */
function createBlankImageDataUrl(width: number, height: number): string {
    const canvas = document.createElement('canvas');
    canvas.width = width;
    canvas.height = height;
    const context = canvas.getContext('2d');
    if (context) {
        context.fillStyle = '#ffffff';
        context.fillRect(0, 0, width, height);
    }

    return canvas.toDataURL('image/png');
}

onMounted(() => {
    editor = new ImageEditor(editorContainer.value!, {
        includeUI: {
            // tui-image-editor merges this object over its own internal
            // default (`{ path: '', name: '' }`) via a shallow, own-key
            // copy that does not skip `undefined` values — passing
            // `undefined` here would clobber that default and crash
            // initCanvas() when it reads `loadImageInfo.path` off
            // `undefined`. A real blank image keeps its own falsy check
            // intact AND correctly drives resizeEditor() (see
            // createBlankImageDataUrl() above).
            loadImage: props.initialImageUrl
                ? {
                      path: props.initialImageUrl,
                      name: props.initialImageName ?? 'design',
                  }
                : {
                      path: createBlankImageDataUrl(
                          CSS_MAX_WIDTH,
                          CSS_MAX_HEIGHT,
                      ),
                      name: 'blank',
                  },
            // The outer chrome box (header + canvas area + bottom menu
            // bar) is a distinct sizing concern from cssMaxWidth/
            // cssMaxHeight below, which only cap the canvas itself — see
            // EDITOR_CHROME_HEIGHT above. Without this, uiSize defaults to
            // 100%/100%, which collapses to tui-image-editor's own
            // min-height: 300px fallback since our wrapper's parent has no
            // explicit height, clipping the canvas to a sliver.
            uiSize: {
                width: '100%',
                height: `${CSS_MAX_HEIGHT + EDITOR_CHROME_HEIGHT}px`,
            },
            theme: {},
            menu: [
                'crop',
                'flip',
                'rotate',
                'draw',
                'shape',
                'icon',
                'text',
                'filter',
            ],
            menuBarPosition: 'bottom',
        },
        cssMaxWidth: CSS_MAX_WIDTH,
        cssMaxHeight: CSS_MAX_HEIGHT,
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

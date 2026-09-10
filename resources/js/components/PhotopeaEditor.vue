<script setup lang="ts">
import { Maximize2, Minimize2 } from '@lucide/vue';
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { Button } from '@/components/ui/button';

const props = defineProps<{
    /** Signed temporaryUrl() for an existing design file, or null for a blank canvas. */
    initialImageUrl: string | null;
}>();

/**
 * Photopea runs entirely in the browser: the iframe is served from
 * photopea.com, but image data never leaves this machine — it is handed to
 * the editor over postMessage and read back the same way. The origin is
 * pinned so a message from any other frame is ignored.
 */
const PHOTOPEA_ORIGIN = 'https://www.photopea.com';

/** Default blank canvas, in pixels at 72dpi. Artists resize from Image > Canvas Size. */
const BLANK_CANVAS = { width: 1600, height: 1200, resolution: 72 } as const;

/** Photopea can take a while to flatten a large document. */
const EXPORT_TIMEOUT_MS = 60_000;

const frame = ref<HTMLIFrameElement | null>(null);
const wrapper = ref<HTMLElement | null>(null);
const isReady = ref(false);
const isFullscreen = ref(false);

/**
 * Resolver for the ArrayBuffer that `saveToOE` posts back. Only one export
 * is ever in flight — the Send for Review button disables itself while its
 * request is processing.
 */
let pendingExport: ((buffer: ArrayBuffer) => void) | null = null;

const EDITOR_SRC = `${PHOTOPEA_ORIGIN}#${encodeURIComponent(
    JSON.stringify({ files: [], environment: {} }),
)}`;

/**
 * Assigned in `onMounted`, never in the initial render.
 *
 * An iframe whose `src` Vue sets while building the element does not
 * navigate — observed on the path where this component mounts with the page
 * rather than after a click: the element is in the DOM, sized, with a
 * contentWindow, and Photopea never boots. Reassigning `src` afterwards
 * loads it every time. Setting it post-mount also guarantees our `message`
 * listener is attached before Photopea can announce itself, so the "done"
 * handshake cannot be missed.
 */
const editorSrc = ref<string | undefined>(undefined);

function postToEditor(message: string | ArrayBuffer): void {
    frame.value?.contentWindow?.postMessage(message, PHOTOPEA_ORIGIN);
}

/**
 * Load the existing design by posting its BYTES rather than its URL.
 *
 * Handing Photopea the URL would make the photopea.com-origin iframe fetch
 * it, which our signed `temporaryUrl` responses answer without CORS headers.
 * Fetching here — same-origin, with the session cookie — and forwarding the
 * ArrayBuffer sidesteps that entirely, and works for PSD as well as flat
 * raster files.
 */
async function loadInitialDocument(): Promise<void> {
    if (props.initialImageUrl === null) {
        postToEditor(
            `app.documents.add(${BLANK_CANVAS.width}, ${BLANK_CANVAS.height}, ${BLANK_CANVAS.resolution}, "Design");`,
        );

        return;
    }

    const response = await fetch(props.initialImageUrl);

    postToEditor(await response.arrayBuffer());
}

/**
 * Photopea's protocol: it posts the literal string "done" once it has
 * finished booting, and again after processing each message it is sent.
 * Binary results (from `saveToOE`) arrive as their own ArrayBuffer message
 * immediately before that trailing "done".
 */
function onEditorMessage(event: MessageEvent): void {
    if (event.origin !== PHOTOPEA_ORIGIN) {
        return;
    }

    if (event.data instanceof ArrayBuffer) {
        pendingExport?.(event.data);
        pendingExport = null;

        return;
    }

    if (event.data === 'done' && !isReady.value) {
        isReady.value = true;
        void loadInitialDocument();
    }
}

function onFullscreenChange(): void {
    isFullscreen.value = document.fullscreenElement === wrapper.value;
}

onMounted(() => {
    window.addEventListener('message', onEditorMessage);
    document.addEventListener('fullscreenchange', onFullscreenChange);
    editorSrc.value = EDITOR_SRC;
});

onBeforeUnmount(() => {
    window.removeEventListener('message', onEditorMessage);
    document.removeEventListener('fullscreenchange', onFullscreenChange);
    pendingExport = null;

    if (document.fullscreenElement === wrapper.value) {
        void document.exitFullscreen();
    }
});

/** Flattened PNG export — the same contract the previous editor exposed, now async. */
function exportPng(): Promise<Blob> {
    return new Promise((resolve, reject) => {
        const timer = setTimeout(() => {
            pendingExport = null;
            reject(new Error('Photopea did not return the design in time.'));
        }, EXPORT_TIMEOUT_MS);

        pendingExport = (buffer) => {
            clearTimeout(timer);
            resolve(new Blob([buffer], { type: 'image/png' }));
        };

        postToEditor('app.activeDocument.saveToOE("png");');
    });
}

async function toggleFullscreen(): Promise<void> {
    if (document.fullscreenElement === wrapper.value) {
        await document.exitFullscreen();

        return;
    }

    // Rejects when the browser blocks it (no user gesture, or a permissions
    // policy). Swallowed deliberately: the toggle stays available, and the
    // editor is perfectly usable at its normal size.
    await wrapper.value?.requestFullscreen().catch(() => undefined);
}

defineExpose({ exportPng, toggleFullscreen });
</script>

<template>
    <div
        ref="wrapper"
        :class="[
            'bg-card relative flex w-full flex-col overflow-hidden rounded-lg border',
            isFullscreen ? 'h-screen rounded-none border-0' : 'h-[70vh]',
        ]"
    >
        <div
            class="border-border flex items-center justify-between gap-2 border-b px-3 py-2"
        >
            <p class="text-muted-foreground text-sm">
                {{
                    isReady
                        ? 'Photopea — your work stays on this computer until you send it for review.'
                        : 'Loading the editor…'
                }}
            </p>
            <Button
                type="button"
                variant="outline"
                size="sm"
                data-test="toggle-editor-fullscreen-button"
                @click="toggleFullscreen"
            >
                <Maximize2 v-if="!isFullscreen" class="size-4" />
                <Minimize2 v-else class="size-4" />
                {{ isFullscreen ? 'Exit Full Screen' : 'Full Screen' }}
            </Button>
        </div>

        <iframe
            v-if="editorSrc"
            ref="frame"
            :src="editorSrc"
            title="Photopea design editor"
            allow="fullscreen"
            class="min-h-0 w-full flex-1 border-0"
            data-test="photopea-frame"
        />
    </div>
</template>

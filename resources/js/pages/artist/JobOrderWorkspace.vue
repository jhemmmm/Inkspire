<script setup lang="ts">
import { Form, Head, router, setLayoutProps, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import JobOrderWorkspaceController from '@/actions/App/Http/Controllers/Artist/JobOrderWorkspaceController';
import InputError from '@/components/InputError.vue';
import ToastImageEditor from '@/components/ToastImageEditor.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Textarea } from '@/components/ui/textarea';
import { artistNavItems } from '@/config/nav/artist';
import { dashboard } from '@/routes/artist';
import { show } from '@/routes/artist/job-orders';
import {
    sendForReview as sendForReviewRoute,
    start,
} from '@/routes/artist/job-orders/design';

const props = defineProps<{
    jobOrder: {
        id: number;
        description: string;
        status: string;
        consultation_notes: string | null;
        canEditConsultation: boolean;
    };
    design: {
        initialImageUrl: string | null;
        canEdit: boolean;
    };
}>();

defineOptions({
    layout: {
        navItems: artistNavItems,
    },
});

setLayoutProps({
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
        {
            title: props.jobOrder.description,
            href: show(props.jobOrder.id),
        },
    ],
});

// D-11: an object URL from a locally-picked reference image, never uploaded
// to the server before Send for Review.
const referenceImageUrl = ref<string | null>(null);
const started = ref(props.design.initialImageUrl !== null);
const editorInitialUrl = computed(
    () => props.design.initialImageUrl ?? referenceImageUrl.value,
);

const fileInputRef = ref<HTMLInputElement | null>(null);
const editorRef = ref<InstanceType<typeof ToastImageEditor> | null>(null);
const sendForReviewForm = useForm<{ file: File | null }>({ file: null });

function onStartBlankCanvas(): void {
    started.value = true;
    router.patch(start.url(props.jobOrder.id), {}, { preserveScroll: true });
}

function onReferenceFileChosen(event: Event): void {
    const file = (event.target as HTMLInputElement).files?.[0];

    if (!file) {
        return;
    }

    referenceImageUrl.value = URL.createObjectURL(file);
    started.value = true;
    router.patch(start.url(props.jobOrder.id), {}, { preserveScroll: true });
}

/** D-10: exports and submits a flattened raster snapshot, never a re-editable layered project. */
async function sendForReview(): Promise<void> {
    const dataUrl = editorRef.value!.exportPng();
    const blob = await (await fetch(dataUrl)).blob();
    sendForReviewForm.file = new File([blob], 'design.png', {
        type: 'image/png',
    });

    sendForReviewForm.post(sendForReviewRoute.url(props.jobOrder.id), {
        forceFormData: true,
        preserveScroll: true,
    });
}
</script>

<template>
    <Head :title="jobOrder.description" />

    <div
        class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4"
    >
        <h1 class="text-[28px] leading-[1.2] font-semibold">
            {{ jobOrder.description }}
        </h1>

        <div class="flex flex-col gap-6">
            <Card>
                <CardHeader>
                    <CardTitle>Consultation Notes</CardTitle>
                </CardHeader>
                <CardContent>
                    <Form
                        v-if="jobOrder.canEditConsultation"
                        v-bind="
                            JobOrderWorkspaceController.updateConsultation.form(
                                jobOrder.id,
                            )
                        "
                        :options="{ preserveScroll: true }"
                        class="space-y-4"
                        v-slot="{ errors, processing }"
                    >
                        <Textarea
                            name="consultation_notes"
                            :default-value="jobOrder.consultation_notes ?? ''"
                            rows="6"
                        />
                        <InputError :message="errors.consultation_notes" />
                        <Button
                            type="submit"
                            :disabled="processing"
                            data-test="save-consultation-notes-button"
                        >
                            Save Consultation Notes
                        </Button>
                    </Form>
                    <p v-else class="text-sm">
                        {{ jobOrder.consultation_notes ?? '—' }}
                    </p>
                </CardContent>
            </Card>

            <Card v-if="jobOrder.status !== 'assigned'">
                <CardHeader>
                    <CardTitle>Design</CardTitle>
                </CardHeader>
                <CardContent class="space-y-4">
                    <p
                        v-if="jobOrder.status === 'pending_review'"
                        class="text-muted-foreground text-sm"
                        data-test="design-pending-review-note"
                    >
                        Waiting on the client's verdict.
                    </p>
                    <p
                        v-else-if="!design.canEdit"
                        class="text-muted-foreground text-sm"
                    >
                        This design is locked.
                    </p>
                    <div v-else-if="!started" class="flex items-center gap-2">
                        <Button
                            type="button"
                            data-test="start-blank-canvas-button"
                            @click="onStartBlankCanvas"
                        >
                            Start from Blank Canvas
                        </Button>
                        <Button
                            type="button"
                            data-test="import-reference-image-button"
                            @click="fileInputRef?.click()"
                        >
                            Import Reference Image
                        </Button>
                        <input
                            ref="fileInputRef"
                            type="file"
                            accept="image/*"
                            class="hidden"
                            @change="onReferenceFileChosen"
                        />
                    </div>
                    <div v-else class="space-y-4">
                        <ToastImageEditor
                            ref="editorRef"
                            :initial-image-url="editorInitialUrl"
                        />
                        <Button
                            type="button"
                            :disabled="sendForReviewForm.processing"
                            data-test="send-for-review-button"
                            @click="sendForReview"
                        >
                            Send for Review
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </div>
    </div>
</template>

<script setup lang="ts">
import { Form, Head, router, setLayoutProps, useForm } from '@inertiajs/vue3';
import { readPsd } from 'ag-psd';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import DesignEditorController from '@/actions/App/Http/Controllers/Artist/DesignEditorController';
import JobOrderWorkspaceController from '@/actions/App/Http/Controllers/Artist/JobOrderWorkspaceController';
import InputError from '@/components/InputError.vue';
import ToastImageEditor from '@/components/ToastImageEditor.vue';
import {
    AlertDialog,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
    AlertDialogTrigger,
} from '@/components/ui/alert-dialog';
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
    review: {
        canRecordVerdict: boolean;
        revisionLogs: {
            id: number;
            submitted_at: string;
            outcome: string | null;
            reviewed_at: string | null;
        }[];
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
// Single source of truth for the "waiting on the client's verdict" note —
// mirrors review.canRecordVerdict, the same server-computed flag that gates
// the Review card below.
const isPendingReview = computed(() => props.review.canRecordVerdict);

const fileInputRef = ref<HTMLInputElement | null>(null);
const editorRef = ref<InstanceType<typeof ToastImageEditor> | null>(null);
const sendForReviewForm = useForm<{ file: File | null }>({ file: null });

function onStartBlankCanvas(): void {
    started.value = true;
    router.patch(start.url(props.jobOrder.id), {}, { preserveScroll: true });
}

/**
 * Returns null on BOTH a thrown parse exception (corrupt/unsupported PSD)
 * AND a successfully-parsed PSD with no composite image data (`psd.canvas`
 * undefined, e.g. a file saved without "Maximize Compatibility") — both
 * failure modes fail loud identically per D-23.
 */
async function readPsdAsFlattenedDataUrl(file: File): Promise<string | null> {
    try {
        const buffer = await file.arrayBuffer();
        const psd = readPsd(buffer);

        return psd.canvas ? psd.canvas.toDataURL('image/png') : null;
    } catch {
        return null;
    }
}

async function onReferenceFileChosen(event: Event): Promise<void> {
    const file = (event.target as HTMLInputElement).files?.[0];

    if (!file) {
        return;
    }

    if (file.name.toLowerCase().endsWith('.psd')) {
        const dataUrl = await readPsdAsFlattenedDataUrl(file);

        if (!dataUrl) {
            toast.error(
                "Couldn't read this PSD — try exporting a flattened PNG/JPG from Photoshop.",
            );
            (event.target as HTMLInputElement).value = '';
            return;
        }

        referenceImageUrl.value = dataUrl;
        started.value = true;
        router.patch(
            start.url(props.jobOrder.id),
            {},
            { preserveScroll: true },
        );
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

function outcomeLabel(outcome: string | null): string {
    if (outcome === 'approved') {
        return 'Approved';
    }

    if (outcome === 'changes_requested') {
        return 'Changes Requested';
    }

    return 'Pending';
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
                        v-if="isPendingReview"
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
                            accept="image/*,.psd"
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

            <Card v-if="review.canRecordVerdict">
                <CardHeader>
                    <CardTitle>Review</CardTitle>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div class="flex items-center gap-2">
                        <AlertDialog>
                            <AlertDialogTrigger as-child>
                                <Button
                                    type="button"
                                    data-test="client-approved-button"
                                >
                                    Client Approved
                                </Button>
                            </AlertDialogTrigger>
                            <AlertDialogContent>
                                <AlertDialogHeader>
                                    <AlertDialogTitle>
                                        Approve this design?
                                    </AlertDialogTitle>
                                    <AlertDialogDescription>
                                        Once approved, this design file becomes
                                        read-only. Only an Owner can unlock it
                                        for further edits.
                                    </AlertDialogDescription>
                                </AlertDialogHeader>
                                <AlertDialogFooter>
                                    <AlertDialogCancel>
                                        Cancel
                                    </AlertDialogCancel>
                                    <Form
                                        v-bind="
                                            DesignEditorController.approve.form(
                                                jobOrder.id,
                                            )
                                        "
                                        :options="{ preserveScroll: true }"
                                        v-slot="{ processing }"
                                    >
                                        <Button
                                            type="submit"
                                            :disabled="processing"
                                            data-test="confirm-approve-button"
                                        >
                                            Confirm Approval
                                        </Button>
                                    </Form>
                                </AlertDialogFooter>
                            </AlertDialogContent>
                        </AlertDialog>

                        <Form
                            v-bind="
                                DesignEditorController.requestChanges.form(
                                    jobOrder.id,
                                )
                            "
                            :options="{ preserveScroll: true }"
                            v-slot="{ processing }"
                        >
                            <Button
                                type="submit"
                                variant="outline"
                                :disabled="processing"
                                data-test="request-changes-button"
                            >
                                Client Requested Changes
                            </Button>
                        </Form>
                    </div>

                    <div class="flex flex-col gap-1">
                        <p
                            v-for="log in review.revisionLogs"
                            :key="log.id"
                            class="text-muted-foreground text-sm"
                        >
                            {{ new Date(log.submitted_at).toLocaleString() }}
                            — {{ outcomeLabel(log.outcome) }}
                        </p>
                    </div>
                </CardContent>
            </Card>
        </div>
    </div>
</template>

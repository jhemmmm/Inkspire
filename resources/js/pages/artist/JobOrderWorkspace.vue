<script setup lang="ts">
import { Form, Head, router, setLayoutProps, useForm } from '@inertiajs/vue3';
import {
    ClipboardList,
    CircleCheck,
    FileDown,
    ImageUp,
    MessagesSquare,
    Palette,
    Zap,
} from '@lucide/vue';
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import DesignEditorController from '@/actions/App/Http/Controllers/Artist/DesignEditorController';
import JobOrderWorkspaceController from '@/actions/App/Http/Controllers/Artist/JobOrderWorkspaceController';
import AlertError from '@/components/AlertError.vue';
import InputError from '@/components/InputError.vue';
import PageContainer from '@/components/PageContainer.vue';
import PageHeader from '@/components/PageHeader.vue';
import PhotopeaEditor from '@/components/PhotopeaEditor.vue';
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
import { Badge } from '@/components/ui/badge';
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

/**
 * The artist-facing name for each stage this workspace can be opened in.
 */
function jobOrderStatusLabel(status: string): string {
    switch (status) {
        case 'assigned':
            return 'Assigned';
        case 'in_consultation':
            return 'In Consultation';
        case 'in_design':
            return 'In Design';
        case 'pending_review':
            return 'Pending Review';
        case 'design_approved':
            return 'Design Approved';
        default:
            return 'In Progress';
    }
}

const props = defineProps<{
    jobOrder: {
        id: number;
        number: string | null;
        description: string;
        status: string;
        type: string;
        is_rush: boolean;
        deadline: string | null;
        customer_name: string | null;
        customer_organization: string | null;
        consultation_notes: string | null;
        client_notes: string | null;
        print_size: string | null;
        width_ft: string | null;
        height_ft: string | null;
        quantity: number | null;
        validation_failure_reason: string | null;
        canEditConsultation: boolean;
    };
    design: {
        customerFileUrl: string | null;
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

const started = ref(props.design.initialImageUrl !== null);
// Single source of truth for the "waiting on the client's verdict" note —
// mirrors review.canRecordVerdict, the same server-computed flag that gates
// the Review card below.
const isPendingReview = computed(() => props.review.canRecordVerdict);

const editorRef = ref<InstanceType<typeof PhotopeaEditor> | null>(null);
const sendForReviewForm = useForm<{ file: File | null }>({ file: null });

/**
 * Opening the editor goes straight to full screen — a print layout is not
 * something you judge in a 300px box. `nextTick` is a microtask, so the
 * click's user-activation still covers the requestFullscreen() call once the
 * editor has been rendered; if a browser refuses anyway, PhotopeaEditor
 * swallows the rejection and its own Full Screen button remains.
 */
async function onStartBlankCanvas(): Promise<void> {
    started.value = true;
    router.patch(start.url(props.jobOrder.id), {}, { preserveScroll: true });

    await nextTick();
    await editorRef.value?.toggleFullscreen();
}

const isExporting = ref(false);

const chosenFile = ref<File | null>(null);
const chosenFilePreviewUrl = ref<string | null>(null);
const fileInputRef = ref<HTMLInputElement | null>(null);

watch(chosenFile, (file) => {
    if (chosenFilePreviewUrl.value !== null) {
        URL.revokeObjectURL(chosenFilePreviewUrl.value);
    }

    chosenFilePreviewUrl.value =
        file === null ? null : URL.createObjectURL(file);
});

onBeforeUnmount(() => {
    if (chosenFilePreviewUrl.value !== null) {
        URL.revokeObjectURL(chosenFilePreviewUrl.value);
    }
});

function openFilePicker(): void {
    fileInputRef.value?.click();
}

function onFileChosen(event: Event): void {
    const input = event.target as HTMLInputElement;

    chosenFile.value = input.files?.[0] ?? null;
    sendForReviewForm.clearErrors();
    input.value = '';
}

function clearChosenFile(): void {
    chosenFile.value = null;
    sendForReviewForm.clearErrors();
}

/** D-10: exports and submits a flattened raster snapshot, never a re-editable layered project. */
async function sendForReview(): Promise<void> {
    if (chosenFile.value !== null) {
        sendForReviewForm.file = chosenFile.value;
        sendForReviewForm.post(sendForReviewRoute.url(props.jobOrder.id), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                chosenFile.value = null;
            },
        });

        return;
    }

    isExporting.value = true;

    try {
        const blob = await editorRef.value!.exportPng();

        sendForReviewForm.file = new File([blob], 'design.png', {
            type: 'image/png',
        });
    } catch {
        // The editor round-trip is the one step here that can hang without
        // the form ever knowing, so it fails loudly rather than leaving a
        // disabled button behind.
        toast.error(
            "Couldn't read the design out of the editor. Try again, and check the editor finished loading.",
        );

        return;
    } finally {
        isExporting.value = false;
    }

    sendForReviewForm.post(sendForReviewRoute.url(props.jobOrder.id), {
        forceFormData: true,
        preserveScroll: true,
    });
}

/** Matches the 'en-PH' long-date convention used across the other portals. */
const deadlineLabel = computed(() =>
    props.jobOrder.deadline === null
        ? 'No deadline set'
        : new Date(props.jobOrder.deadline).toLocaleDateString('en-PH', {
              month: 'long',
              day: 'numeric',
              year: 'numeric',
          }),
);

/** The artist-facing label for the two intake routes. */
const typeLabel = computed(() =>
    props.jobOrder.type === 'type_a' ? 'Type A' : 'Type B',
);

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

    <PageContainer>
        <PageHeader
            :title="jobOrder.description"
            description="Capture the consultation, build the layout, then send it for review."
        >
            <template #actions>
                <Badge variant="outline" data-test="workspace-type-badge">
                    {{ typeLabel }}
                </Badge>
                <Badge
                    v-if="jobOrder.is_rush"
                    variant="outline"
                    class="border-brand/40 text-brand"
                    data-test="workspace-rush-badge"
                >
                    <Zap class="size-3" />
                    Rush
                </Badge>
                <Badge variant="secondary">
                    {{ jobOrderStatusLabel(jobOrder.status) }}
                </Badge>
            </template>
        </PageHeader>

        <AlertError
            v-if="jobOrder.validation_failure_reason"
            title="This file needs work before it can be printed"
            :errors="[jobOrder.validation_failure_reason]"
        />

        <Card>
            <CardHeader :icon="ClipboardList">
                <CardTitle>Job Brief</CardTitle>
            </CardHeader>
            <CardContent class="flex flex-col gap-4">
                <dl
                    class="grid grid-cols-1 gap-4 @lg:grid-cols-2 @3xl:grid-cols-3"
                >
                    <div class="flex flex-col gap-1">
                        <dt class="text-muted-foreground text-sm">Job Order</dt>
                        <dd class="font-medium tabular-nums">
                            {{ jobOrder.number ?? '—' }}
                        </dd>
                    </div>
                    <div class="flex flex-col gap-1">
                        <dt class="text-muted-foreground text-sm">Customer</dt>
                        <dd class="font-medium">
                            {{ jobOrder.customer_name ?? '—' }}
                            <span
                                v-if="jobOrder.customer_organization"
                                class="text-muted-foreground"
                            >
                                · {{ jobOrder.customer_organization }}
                            </span>
                        </dd>
                    </div>
                    <div class="flex flex-col gap-1">
                        <dt class="text-muted-foreground text-sm">Deadline</dt>
                        <dd class="font-medium">{{ deadlineLabel }}</dd>
                    </div>
                    <div class="flex flex-col gap-1">
                        <dt class="text-muted-foreground text-sm">
                            Print Size
                        </dt>
                        <dd class="font-medium">
                            {{ jobOrder.print_size ?? '—' }}
                        </dd>
                    </div>
                    <div class="flex flex-col gap-1">
                        <dt class="text-muted-foreground text-sm">Size</dt>
                        <dd class="font-medium tabular-nums">
                            {{
                                jobOrder.width_ft && jobOrder.height_ft
                                    ? `${jobOrder.width_ft} ft × ${jobOrder.height_ft} ft`
                                    : '—'
                            }}
                        </dd>
                    </div>
                    <div class="flex flex-col gap-1">
                        <dt class="text-muted-foreground text-sm">Quantity</dt>
                        <dd class="font-medium tabular-nums">
                            {{ jobOrder.quantity ?? '—' }}
                        </dd>
                    </div>
                </dl>

                <div class="flex flex-col gap-1">
                    <dt class="text-muted-foreground text-sm">
                        Client Instructions
                    </dt>
                    <p class="whitespace-pre-line">
                        {{
                            jobOrder.client_notes ||
                            'The customer left no instructions.'
                        }}
                    </p>
                </div>

                <div
                    v-if="design.customerFileUrl"
                    class="flex flex-col items-start gap-1"
                >
                    <dt class="text-muted-foreground text-sm">
                        Customer's File
                    </dt>
                    <a
                        :href="design.customerFileUrl"
                        target="_blank"
                        rel="noopener"
                        class="text-primary inline-flex items-center gap-2 text-sm font-medium underline-offset-4 hover:underline"
                        data-test="customer-file-link"
                    >
                        <FileDown class="size-4" />
                        Open the file the customer supplied
                    </a>
                </div>
            </CardContent>
        </Card>

        <Card>
            <CardHeader :icon="MessagesSquare">
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
            <CardHeader :icon="Palette">
                <CardTitle>Design</CardTitle>
            </CardHeader>
            <CardContent class="space-y-4">
                <div v-if="isPendingReview" class="space-y-2">
                    <img
                        v-if="design.initialImageUrl"
                        :src="design.initialImageUrl"
                        alt="Submitted design"
                        class="w-full rounded-lg border"
                        data-test="design-pending-review-image"
                    />
                    <p
                        class="text-muted-foreground text-sm"
                        data-test="design-pending-review-note"
                    >
                        Waiting on the client's verdict.
                    </p>
                </div>
                <div v-else-if="!design.canEdit" class="space-y-2">
                    <img
                        v-if="design.initialImageUrl"
                        :src="design.initialImageUrl"
                        alt="Approved design"
                        class="w-full rounded-lg border"
                    />
                    <p class="text-muted-foreground text-sm">
                        This design is locked.
                    </p>
                </div>
                <div v-else class="space-y-4">
                    <input
                        ref="fileInputRef"
                        type="file"
                        accept="image/png,image/jpeg"
                        class="sr-only"
                        tabindex="-1"
                        data-test="design-file-input"
                        @change="onFileChosen"
                    />

                    <div v-if="chosenFile" class="space-y-3">
                        <img
                            v-if="chosenFilePreviewUrl"
                            :src="chosenFilePreviewUrl"
                            alt="Preview of the chosen design"
                            class="bg-muted max-h-[60vh] w-full rounded-lg border object-contain"
                            data-test="chosen-file-preview"
                        />
                        <p class="text-muted-foreground text-sm break-all">
                            {{ chosenFile.name }}
                        </p>
                        <div class="flex flex-wrap items-center gap-2">
                            <Button
                                type="button"
                                :disabled="sendForReviewForm.processing"
                                data-test="send-for-review-button"
                                @click="sendForReview"
                            >
                                Send for Review
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                data-test="choose-different-file-button"
                                @click="openFilePicker"
                            >
                                <ImageUp class="size-4" />
                                Choose a different file
                            </Button>
                            <Button
                                type="button"
                                variant="ghost"
                                data-test="use-editor-instead-button"
                                @click="clearChosenFile"
                            >
                                Use the editor instead
                            </Button>
                        </div>
                    </div>

                    <div
                        v-if="!started && !chosenFile"
                        class="flex flex-col items-start gap-3"
                    >
                        <p class="text-muted-foreground text-sm">
                            Upload a finished PNG or JPG, or open Photopea full
                            screen. Photopea reads PSD, AI, XD, Sketch and the
                            usual image formats, and you can leave full screen
                            at any time.
                        </p>
                        <div class="flex flex-wrap items-center gap-2">
                            <Button
                                type="button"
                                data-test="upload-design-button"
                                @click="openFilePicker"
                            >
                                <ImageUp class="size-4" />
                                Upload a Design
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                data-test="start-blank-canvas-button"
                                @click="onStartBlankCanvas"
                            >
                                <Palette class="size-4" />
                                Start from Blank Canvas
                            </Button>
                        </div>
                    </div>

                    <!--
                        Hidden, not unmounted, while an upload is being
                        previewed: unmounting would throw away whatever the
                        artist had drawn in the editor.
                    -->
                    <div v-if="started" v-show="!chosenFile" class="space-y-4">
                        <PhotopeaEditor
                            ref="editorRef"
                            :initial-image-url="design.initialImageUrl"
                        />
                        <div class="flex flex-wrap items-center gap-2">
                            <Button
                                type="button"
                                :disabled="
                                    sendForReviewForm.processing || isExporting
                                "
                                data-test="send-for-review-button"
                                @click="sendForReview"
                            >
                                {{
                                    isExporting
                                        ? 'Reading the design…'
                                        : 'Send for Review'
                                }}
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                data-test="upload-design-button"
                                @click="openFilePicker"
                            >
                                <ImageUp class="size-4" />
                                Upload a Design
                            </Button>
                        </div>
                    </div>

                    <InputError :message="sendForReviewForm.errors.file" />
                </div>
            </CardContent>
        </Card>

        <Card v-if="review.canRecordVerdict">
            <CardHeader :icon="CircleCheck">
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
                                    read-only. Only an Admin can unlock it for
                                    further edits.
                                </AlertDialogDescription>
                            </AlertDialogHeader>
                            <AlertDialogFooter>
                                <AlertDialogCancel> Cancel </AlertDialogCancel>
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
    </PageContainer>
</template>

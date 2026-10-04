<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import {
    ArrowRight,
    Check,
    CircleCheck,
    Clock,
    Maximize2,
    PencilLine,
    TriangleAlert,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import {
    AlertDialog,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
    AlertDialogTrigger,
} from '@/components/alert-dialog';
import InkCard from '@/components/InkCard.vue';
import PublicNotice from '@/components/PublicNotice.vue';
import PublicPage from '@/components/PublicPage.vue';
import RequestDesignChangesDialog from '@/components/RequestDesignChangesDialog.vue';
import { Button } from '@/components/ui/button';
import { home } from '@/routes';

/**
 * The customer's own page for approving a layout, reached from an emailed
 * signed link. Dressed like the homepage (logo, ink bar, the same soft
 * lift) so it reads as the shop's and not as a stray form.
 *
 * `expired` is rendered by the exception handler for a bad or lapsed
 * signature, where there is no job order to name, so every order prop is
 * optional.
 */
const props = defineProps<{
    state: 'active' | 'stale' | 'closed' | 'expired';
    jobOrderDescription?: string;
    jobOrderNumber?: string | null;
    trackingUrl?: string;
    imageUrl?: string;
    approveUrl?: string;
    requestChangesUrl?: string;
    outcome?: string | null;
}>();

const confirmingApproval = ref(false);

/** The three states that have nothing left to decide. */
const notice = computed(() => {
    if (props.state === 'closed' && props.outcome === 'approved') {
        return {
            icon: CircleCheck,
            tone: 'bg-success/15 text-success',
            title: 'Design approved',
            body: 'Thank you. Your order now moves on to printing. You can follow it, and pay for it, from your order page.',
        };
    }

    if (props.state === 'closed') {
        return {
            icon: PencilLine,
            tone: 'bg-accent text-accent-foreground',
            title: 'Changes requested',
            body: 'Your feedback has been sent to our artist. When the revised design is ready, we will email you a link to review the new version.',
        };
    }

    if (props.state === 'stale') {
        return {
            icon: TriangleAlert,
            tone: 'bg-warning/15 text-warning',
            title: 'A newer version is waiting',
            body: 'This design has changed since this link was sent. Check your latest email for the link to the new version.',
        };
    }

    return {
        icon: Clock,
        tone: 'bg-muted text-muted-foreground',
        title: 'This link has expired',
        body: 'Design review links work for 7 days. Please contact the shop if you still need to review this design.',
    };
});
</script>

<template>
    <PublicPage width="max-w-2xl">
        <Head title="Design Review" />

        <template #aside>
            <span class="text-muted-foreground text-sm font-semibold">
                Design review
            </span>
        </template>

        <InkCard>
            <template v-if="state === 'active'">
                <div class="px-6 pt-9 sm:px-10 sm:pt-11">
                    <div class="bg-primary mb-4 h-[3px] w-10 rounded-full" />
                    <p
                        v-if="jobOrderNumber"
                        class="text-muted-foreground text-xs font-semibold tabular-nums"
                        data-test="design-review-number"
                    >
                        Job Order {{ jobOrderNumber }}
                    </p>
                    <h1
                        class="mt-1 text-2xl leading-tight font-extrabold tracking-tight text-balance sm:text-3xl"
                    >
                        Your design is ready to check
                    </h1>
                    <p
                        class="text-muted-foreground mt-2 text-sm leading-relaxed sm:text-base"
                    >
                        This is the layout for
                        <span class="text-foreground font-semibold">
                            {{ jobOrderDescription }} </span
                        >. Check the spelling, the colours and where everything
                        sits.
                    </p>
                </div>

                <figure class="mt-6 px-6 sm:px-10">
                    <a
                        :href="imageUrl"
                        target="_blank"
                        rel="noopener"
                        class="bg-muted border-border focus-visible:outline-ring block overflow-hidden rounded-xl border focus-visible:outline-2 focus-visible:outline-offset-2"
                        data-test="design-review-full-size-link"
                    >
                        <img
                            :src="imageUrl"
                            :alt="`Design preview for ${jobOrderDescription}`"
                            class="mx-auto max-h-[60vh] w-full object-contain"
                        />
                    </a>
                    <figcaption
                        class="text-muted-foreground mt-2 flex items-center gap-1.5 text-sm"
                    >
                        <Maximize2 aria-hidden="true" class="size-3.5" />
                        Tap the design to open it full size.
                    </figcaption>
                </figure>

                <!-- Sticky: on a phone the two buttons would otherwise sit
                         a screen below a tall design. -->
                <div
                    class="bg-card/95 border-border sticky bottom-0 mt-6 flex flex-col gap-3 border-t px-6 py-4 backdrop-blur sm:px-10 sm:py-5"
                >
                    <p class="text-muted-foreground text-sm">
                        Nothing is printed until you approve it.
                    </p>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <AlertDialog v-model:open="confirmingApproval">
                            <AlertDialogTrigger as-child>
                                <Button
                                    type="button"
                                    size="lg"
                                    class="w-full"
                                    data-test="remote-approve-open-button"
                                >
                                    <Check class="size-4" />
                                    Approve design
                                </Button>
                            </AlertDialogTrigger>
                            <AlertDialogContent>
                                <AlertDialogHeader>
                                    <AlertDialogTitle>
                                        Approve this design?
                                    </AlertDialogTitle>
                                    <AlertDialogDescription>
                                        We print exactly what is shown. Once you
                                        approve, the design can no longer be
                                        changed.
                                    </AlertDialogDescription>
                                </AlertDialogHeader>
                                <AlertDialogFooter>
                                    <AlertDialogCancel>
                                        Not yet
                                    </AlertDialogCancel>
                                    <Form
                                        :action="approveUrl"
                                        method="post"
                                        v-slot="{ processing }"
                                        @success="confirmingApproval = false"
                                    >
                                        <Button
                                            type="submit"
                                            class="w-full"
                                            :disabled="processing"
                                            data-test="remote-approve-button"
                                        >
                                            Yes, approve it
                                        </Button>
                                    </Form>
                                </AlertDialogFooter>
                            </AlertDialogContent>
                        </AlertDialog>

                        <RequestDesignChangesDialog
                            :action="requestChangesUrl!"
                        >
                            <Button
                                type="button"
                                variant="outline"
                                size="lg"
                                class="w-full"
                                data-test="remote-request-changes-button"
                            >
                                <PencilLine class="size-4" />
                                Request changes
                            </Button>
                        </RequestDesignChangesDialog>
                    </div>
                </div>
            </template>

            <PublicNotice
                v-else
                :icon="notice.icon"
                :tone="notice.tone"
                :title="notice.title"
                :eyebrow="jobOrderNumber ? `Job Order ${jobOrderNumber}` : null"
                :data-test="`design-review-${state}`"
            >
                {{ notice.body }}
                <template #actions>
                    <Button as-child size="lg">
                        <Link
                            v-if="trackingUrl"
                            :href="trackingUrl"
                            data-test="design-review-tracking-link"
                        >
                            Track your order
                            <ArrowRight class="size-4" />
                        </Link>
                        <Link v-else :href="home()">Go to the homepage</Link>
                    </Button>
                </template>
            </PublicNotice>
        </InkCard>
    </PublicPage>
</template>

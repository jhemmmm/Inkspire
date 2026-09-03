<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';

const props = defineProps<{
    state: 'active' | 'stale' | 'closed' | 'expired';
    jobOrderDescription?: string;
    imageUrl?: string;
    approveUrl?: string;
    requestChangesUrl?: string;
    outcome?: string | null;
}>();

const closedMessage = computed(() =>
    props.outcome === 'approved'
        ? 'This design was already approved.'
        : 'This design was already sent back for changes.',
);
</script>

<template>
    <Head title="Design Review" />

    <div
        class="dark bg-background text-foreground flex min-h-screen items-center justify-center p-8"
    >
        <Card class="w-full max-w-xl">
            <template v-if="state === 'active'">
                <CardHeader>
                    <CardTitle>{{ jobOrderDescription }}</CardTitle>
                    <CardDescription>
                        Review the design below and let us know if it's ready to
                        go.
                    </CardDescription>
                </CardHeader>
                <CardContent class="space-y-6">
                    <img
                        :src="imageUrl"
                        alt="Design preview"
                        class="w-full rounded-lg border"
                    />
                    <div class="flex items-center gap-2">
                        <Form
                            :action="approveUrl"
                            method="post"
                            v-slot="{ processing }"
                        >
                            <Button
                                type="submit"
                                :disabled="processing"
                                data-test="remote-approve-button"
                            >
                                Client Approved
                            </Button>
                        </Form>
                        <Form
                            :action="requestChangesUrl"
                            method="post"
                            v-slot="{ processing }"
                        >
                            <Button
                                type="submit"
                                variant="outline"
                                :disabled="processing"
                                data-test="remote-request-changes-button"
                            >
                                Client Requested Changes
                            </Button>
                        </Form>
                    </div>
                </CardContent>
            </template>

            <template v-else-if="state === 'stale'">
                <CardHeader>
                    <CardTitle>This design has changed</CardTitle>
                    <CardDescription>
                        Check your latest email — a newer version of this design
                        is now waiting for your review.
                    </CardDescription>
                </CardHeader>
            </template>

            <template v-else-if="state === 'closed'">
                <CardHeader>
                    <CardTitle>Already reviewed</CardTitle>
                    <CardDescription>{{ closedMessage }}</CardDescription>
                </CardHeader>
            </template>

            <template v-else-if="state === 'expired'">
                <CardHeader>
                    <CardTitle>This link has expired</CardTitle>
                    <CardDescription>
                        Design review links are valid for 7 days. Please contact
                        the shop if you still need to review this design.
                    </CardDescription>
                </CardHeader>
            </template>
        </Card>
    </div>
</template>

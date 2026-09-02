<script setup lang="ts">
import { Form, Head, setLayoutProps } from '@inertiajs/vue3';
import JobOrderWorkspaceController from '@/actions/App/Http/Controllers/Artist/JobOrderWorkspaceController';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Textarea } from '@/components/ui/textarea';
import { artistNavItems } from '@/config/nav/artist';
import { dashboard } from '@/routes/artist';
import { show } from '@/routes/artist/job-orders';

const props = defineProps<{
    jobOrder: {
        id: number;
        description: string;
        status: string;
        consultation_notes: string | null;
        canEditConsultation: boolean;
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
        </div>
    </div>
</template>

<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import JobOrderQueueController from '@/actions/App/Http/Controllers/Artist/JobOrderQueueController';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableEmpty,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { artistNavItems } from '@/config/nav/artist';
import { dashboard } from '@/routes/artist';
import { show } from '@/routes/artist/job-orders';

interface ArtistJobOrder {
    id: number;
    description: string;
    status: string;
    not_appeared: boolean;
}

defineProps<{
    jobOrders: ArtistJobOrder[];
}>();

defineOptions({
    layout: {
        navItems: artistNavItems,
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: dashboard(),
            },
        ],
    },
});

function statusBadgeVariant(status: string): 'default' | 'secondary' {
    if (status === 'in_consultation') {
        return 'secondary';
    }

    return 'default';
}

function statusLabel(status: string): string {
    if (status === 'in_consultation') {
        return 'In Consultation';
    }

    return 'Assigned';
}
</script>

<template>
    <Head title="Artist Dashboard" />

    <div
        class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4"
    >
        <h1 class="text-[28px] leading-[1.2] font-semibold">
            Artist Dashboard
        </h1>

        <div
            class="border-sidebar-border/70 dark:border-sidebar-border overflow-hidden rounded-xl border"
        >
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Job Order</TableHead>
                        <TableHead>Status</TableHead>
                        <TableHead class="text-right">Actions</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableEmpty v-if="jobOrders.length === 0" :colspan="3">
                        <div class="flex flex-col items-center gap-1">
                            <p class="font-semibold">No job orders assigned</p>
                            <p class="text-muted-foreground">
                                New consultations will appear here automatically
                                when you're set to Available.
                            </p>
                        </div>
                    </TableEmpty>
                    <TableRow
                        v-for="jobOrder in jobOrders"
                        v-else
                        :key="jobOrder.id"
                    >
                        <TableCell>{{ jobOrder.description }}</TableCell>
                        <TableCell>
                            <div class="flex flex-wrap items-center gap-2">
                                <Badge
                                    :variant="
                                        statusBadgeVariant(jobOrder.status)
                                    "
                                >
                                    {{ statusLabel(jobOrder.status) }}
                                </Badge>
                                <Badge
                                    v-if="jobOrder.not_appeared"
                                    variant="outline"
                                    class="text-muted-foreground"
                                >
                                    Not Appeared
                                </Badge>
                            </div>
                        </TableCell>
                        <TableCell>
                            <div class="flex items-center justify-end gap-2">
                                <Form
                                    v-if="jobOrder.status === 'assigned'"
                                    v-bind="
                                        JobOrderQueueController.next.form(
                                            jobOrder.id,
                                        )
                                    "
                                    :options="{ preserveScroll: true }"
                                    v-slot="{ processing }"
                                >
                                    <Button
                                        type="submit"
                                        :disabled="processing"
                                        :data-test="`next-${jobOrder.id}-button`"
                                    >
                                        Next
                                    </Button>
                                </Form>
                                <template
                                    v-else-if="
                                        jobOrder.status === 'in_consultation'
                                    "
                                >
                                    <Form
                                        v-bind="
                                            JobOrderQueueController.forward.form(
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
                                            :data-test="`forward-${jobOrder.id}-button`"
                                        >
                                            Forward
                                        </Button>
                                    </Form>
                                    <Form
                                        v-bind="
                                            JobOrderQueueController.notAppear.form(
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
                                            :data-test="`not-appear-${jobOrder.id}-button`"
                                        >
                                            Not Appear
                                        </Button>
                                    </Form>
                                    <Link
                                        :href="show(jobOrder.id).url"
                                        :data-test="`continue-${jobOrder.id}-link`"
                                    >
                                        <Button type="button" variant="ghost">
                                            Continue
                                        </Button>
                                    </Link>
                                </template>
                            </div>
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>
    </div>
</template>

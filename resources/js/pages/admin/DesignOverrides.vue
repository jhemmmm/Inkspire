<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import DesignFileController from '@/actions/App/Http/Controllers/Admin/DesignFileController';
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
import DataTableCard from '@/components/DataTableCard.vue';
import EmptyState from '@/components/EmptyState.vue';
import PageContainer from '@/components/PageContainer.vue';
import PageHeader from '@/components/PageHeader.vue';
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
import { adminNavItems } from '@/config/nav/admin';
import { index as designOverridesIndex } from '@/routes/admin/design-overrides';

interface LockedJobOrder {
    id: number;
    description: string;
    assigned_artist: { id: number; name: string } | null;
    design_file: { id: number; locked_at: string };
    queue_entry: { customer: { name: string } };
}

defineProps<{
    jobOrders: LockedJobOrder[];
}>();

defineOptions({
    layout: {
        navItems: adminNavItems,
        breadcrumbs: [
            {
                title: 'Design Overrides',
                href: designOverridesIndex(),
            },
        ],
    },
});
</script>

<template>
    <Head title="Design Overrides" />

    <PageContainer>
        <PageHeader
            title="Design Overrides"
            description="Design files locked after approval. Unlocking one lets an artist edit it again."
        />

        <DataTableCard>
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Job Order</TableHead>
                        <TableHead>Customer</TableHead>
                        <TableHead>Artist</TableHead>
                        <TableHead>Locked At</TableHead>
                        <TableHead class="text-right">Actions</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableEmpty v-if="jobOrders.length === 0" :colspan="5">
                        <EmptyState
                            title="No locked designs"
                            description="Design files appear here once a job order reaches Design Approved."
                        />
                    </TableEmpty>
                    <TableRow
                        v-for="jobOrder in jobOrders"
                        v-else
                        :key="jobOrder.id"
                    >
                        <TableCell>{{ jobOrder.description }}</TableCell>
                        <TableCell>
                            {{ jobOrder.queue_entry.customer.name }}
                        </TableCell>
                        <TableCell>
                            {{ jobOrder.assigned_artist?.name ?? '—' }}
                        </TableCell>
                        <TableCell>
                            {{
                                new Date(
                                    jobOrder.design_file.locked_at,
                                ).toLocaleString()
                            }}
                        </TableCell>
                        <TableCell class="text-right">
                            <AlertDialog>
                                <AlertDialogTrigger as-child>
                                    <Button
                                        variant="destructive"
                                        :data-test="`unlock-design-${jobOrder.id}-button`"
                                    >
                                        Unlock Design
                                    </Button>
                                </AlertDialogTrigger>
                                <AlertDialogContent>
                                    <AlertDialogHeader>
                                        <AlertDialogTitle>
                                            Unlock
                                            {{ jobOrder.description }}'s design
                                            file?
                                        </AlertDialogTitle>
                                        <AlertDialogDescription>
                                            This design was locked after client
                                            approval. Unlocking it lets the
                                            Artist edit it again and is recorded
                                            in the audit trail.
                                        </AlertDialogDescription>
                                    </AlertDialogHeader>
                                    <AlertDialogFooter>
                                        <AlertDialogCancel>
                                            Cancel
                                        </AlertDialogCancel>
                                        <Form
                                            v-bind="
                                                DesignFileController.unlock.form(
                                                    jobOrder.design_file.id,
                                                )
                                            "
                                            :options="{
                                                preserveScroll: true,
                                            }"
                                            v-slot="{ processing }"
                                        >
                                            <Button
                                                type="submit"
                                                variant="destructive"
                                                :disabled="processing"
                                                :data-test="`confirm-unlock-${jobOrder.id}-button`"
                                            >
                                                Unlock Design
                                            </Button>
                                        </Form>
                                    </AlertDialogFooter>
                                </AlertDialogContent>
                            </AlertDialog>
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </DataTableCard>
    </PageContainer>
</template>

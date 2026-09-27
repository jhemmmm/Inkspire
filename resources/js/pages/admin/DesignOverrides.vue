<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import DesignFileController from '@/actions/App/Http/Controllers/Owner/DesignFileController';
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
import {
    Table,
    TableBody,
    TableCell,
    TableEmpty,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { ownerNavItems } from '@/config/nav/owner';
import { index as designOverridesIndex } from '@/routes/owner/design-overrides';

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
        navItems: ownerNavItems,
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

    <div
        class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4"
    >
        <h1 class="text-[28px] leading-[1.2] font-semibold">
            Design Overrides
        </h1>

        <div
            class="border-sidebar-border/70 dark:border-sidebar-border overflow-hidden rounded-xl border"
        >
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
                        <div
                            class="flex flex-col items-center gap-1 text-center"
                        >
                            <p class="font-semibold">No locked designs</p>
                            <p class="text-muted-foreground">
                                Design files appear here once a job order
                                reaches Design Approved.
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
        </div>
    </div>
</template>

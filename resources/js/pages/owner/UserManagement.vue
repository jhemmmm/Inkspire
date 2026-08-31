<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import UserManagementController from '@/actions/App/Http/Controllers/Owner/UserManagementController';
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
import { ownerNavItems } from '@/config/nav/owner';
import { index as usersIndex } from '@/routes/owner/users';

interface OwnerUser {
    id: number;
    name: string;
    email: string;
    role: string;
    is_active: boolean;
}

defineProps<{
    users: OwnerUser[];
}>();

defineOptions({
    layout: {
        navItems: ownerNavItems,
        breadcrumbs: [
            {
                title: 'User Management',
                href: usersIndex(),
            },
        ],
    },
});
</script>

<template>
    <Head title="User Management" />

    <div
        class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4"
    >
        <h1 class="text-[28px] leading-[1.2] font-semibold">
            User Management
        </h1>

        <div
            class="border-sidebar-border/70 dark:border-sidebar-border overflow-hidden rounded-xl border"
        >
            <table class="w-full text-sm">
                <thead class="bg-muted">
                    <tr>
                        <th class="p-4 text-left font-semibold">Name</th>
                        <th class="p-4 text-left font-semibold">Email</th>
                        <th class="p-4 text-left font-semibold">Role</th>
                        <th class="p-4 text-left font-semibold">Status</th>
                        <th class="p-4 text-left font-semibold"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="user in users"
                        :key="user.id"
                        class="border-sidebar-border/70 dark:border-sidebar-border border-t"
                    >
                        <td class="p-4">{{ user.name }}</td>
                        <td class="p-4">{{ user.email }}</td>
                        <td class="p-4">{{ user.role }}</td>
                        <td class="p-4">
                            <Badge v-if="!user.is_active" variant="destructive"
                                >Deactivated</Badge
                            >
                            <Badge v-else variant="secondary">Active</Badge>
                        </td>
                        <td class="p-4 text-right">
                            <AlertDialog v-if="user.is_active">
                                <AlertDialogTrigger as-child>
                                    <Button
                                        variant="destructive"
                                        :data-test="`deactivate-user-${user.id}-button`"
                                    >
                                        Deactivate Account
                                    </Button>
                                </AlertDialogTrigger>
                                <AlertDialogContent>
                                    <AlertDialogHeader>
                                        <AlertDialogTitle>
                                            Deactivate {{ user.name }}'s
                                            account?
                                        </AlertDialogTitle>
                                        <AlertDialogDescription>
                                            They will immediately lose the
                                            ability to log in. This does not
                                            delete their data and can be
                                            reversed by reactivating the
                                            account.
                                        </AlertDialogDescription>
                                    </AlertDialogHeader>
                                    <AlertDialogFooter>
                                        <AlertDialogCancel>
                                            Cancel
                                        </AlertDialogCancel>
                                        <Form
                                            v-bind="
                                                UserManagementController.deactivate.form(
                                                    user.id,
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
                                                :data-test="`confirm-deactivate-user-${user.id}-button`"
                                            >
                                                Deactivate Account
                                            </Button>
                                        </Form>
                                    </AlertDialogFooter>
                                </AlertDialogContent>
                            </AlertDialog>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>

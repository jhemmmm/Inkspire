<script setup lang="ts">
import { Form, Head, usePage } from '@inertiajs/vue3';
import { Clock, Lock, Pencil, Plus } from '@lucide/vue';
import { computed, ref } from 'vue';
import UserManagementController from '@/actions/App/Http/Controllers/Admin/UserManagementController';
import InputError from '@/components/InputError.vue';
import PageHeader from '@/components/PageHeader.vue';
import PageContainer from '@/components/PageContainer.vue';
import DataTableCard from '@/components/DataTableCard.vue';
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
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { adminNavItems } from '@/config/nav/admin';
import { roleLabel } from '@/lib/roles';
import { index as usersIndex } from '@/routes/admin/users';

interface ManagedUser {
    id: number;
    name: string;
    email: string;
    role: string;
    artist_label: string | null;
    is_active: boolean;
    is_locked_out: boolean;
    artist_status: string | null;
    exceeded_break_time: boolean;
}

function artistStatusBadgeVariant(
    artistStatus: string,
): 'secondary' | 'outline' | undefined {
    if (artistStatus === 'on_break') {
        return 'secondary';
    }

    if (artistStatus === 'off_shift') {
        return 'outline';
    }

    return undefined;
}

function artistStatusBadgeClass(artistStatus: string): string {
    if (artistStatus === 'available') {
        return 'text-green-600 dark:text-green-400';
    }

    return '';
}

function artistStatusLabel(artistStatus: string): string {
    if (artistStatus === 'available') {
        return 'Available';
    }

    if (artistStatus === 'on_break') {
        return 'On Break';
    }

    return 'Off Shift';
}

/**
 * Every role an Admin may create — mirrors UserPolicy::create(), which now
 * allows all of them. The Owner role this list used to gate against is
 * gone, so there is no longer a narrower set for anyone.
 */
const creatableRoles = [
    'admin',
    'frontline_staff',
    'artist',
    'cashier',
    'production_staff',
    'accounting_staff',
];

defineProps<{
    users: ManagedUser[];
}>();

const newUserRole = ref('');

const page = usePage();
const currentUserId = computed(() => page.props.auth.user.id);

/**
 * One shared edit dialog, driven by the row that opened it. The role lives
 * in its own ref because reka-ui's Select is not a native form control; a
 * hidden input carries it into the submitted form.
 */
const editDialogOpen = ref(false);
const editingUser = ref<ManagedUser | null>(null);
const editUserRole = ref('');
const isEditingSelf = computed(
    () => editingUser.value?.id === currentUserId.value,
);

function openEditDialog(user: ManagedUser): void {
    editingUser.value = user;
    editUserRole.value = user.role;
    editDialogOpen.value = true;
}

defineOptions({
    layout: {
        navItems: adminNavItems,
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

    <PageContainer>
        <PageHeader
            title="User Management"
            description="Create staff accounts and control who can still sign in. Accounts are deactivated, never deleted, so their audit history stays intact."
        >
            <template #actions>
                <Dialog>
                    <DialogTrigger as-child>
                        <Button
                            data-test="new-user-button"
                            @click="newUserRole = ''"
                        >
                            <Plus class="size-4" />
                            New User
                        </Button>
                    </DialogTrigger>
                    <DialogContent>
                        <Form
                            v-bind="UserManagementController.store.form()"
                            :options="{ preserveScroll: true }"
                            class="space-y-4"
                            v-slot="{ errors, processing }"
                        >
                            <DialogHeader>
                                <DialogTitle>Create a new user</DialogTitle>
                                <DialogDescription>
                                    They can log in immediately with the
                                    password you set below.
                                </DialogDescription>
                            </DialogHeader>

                            <div class="grid gap-2">
                                <Label for="create-user-name">Name</Label>
                                <Input
                                    id="create-user-name"
                                    name="name"
                                    autocomplete="name"
                                    placeholder="Full name"
                                />
                                <InputError :message="errors.name" />
                            </div>

                            <div class="grid gap-2">
                                <Label for="create-user-email"
                                    >Email address</Label
                                >
                                <Input
                                    id="create-user-email"
                                    name="email"
                                    type="email"
                                    autocomplete="email"
                                    placeholder="Email address"
                                />
                                <InputError :message="errors.email" />
                            </div>

                            <input
                                type="hidden"
                                name="role"
                                :value="newUserRole"
                            />
                            <div class="grid gap-2">
                                <Label for="create-user-role">Role</Label>
                                <Select v-model="newUserRole">
                                    <SelectTrigger
                                        id="create-user-role"
                                        class="w-full"
                                    >
                                        <SelectValue
                                            placeholder="Choose a role"
                                        />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem
                                            v-for="role in creatableRoles"
                                            :key="role"
                                            :value="role"
                                        >
                                            {{ roleLabel(role) }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <InputError :message="errors.role" />
                            </div>

                            <div class="grid gap-2">
                                <Label for="create-user-password"
                                    >Password</Label
                                >
                                <Input
                                    id="create-user-password"
                                    name="password"
                                    type="password"
                                    autocomplete="new-password"
                                />
                                <InputError :message="errors.password" />
                            </div>

                            <div class="grid gap-2">
                                <Label for="create-user-password-confirmation"
                                    >Confirm Password</Label
                                >
                                <Input
                                    id="create-user-password-confirmation"
                                    name="password_confirmation"
                                    type="password"
                                    autocomplete="new-password"
                                />
                                <InputError
                                    :message="errors.password_confirmation"
                                />
                            </div>

                            <DialogFooter class="gap-2">
                                <DialogClose as-child>
                                    <Button type="button" variant="secondary"
                                        >Cancel</Button
                                    >
                                </DialogClose>
                                <Button
                                    type="submit"
                                    :disabled="processing"
                                    data-test="create-user-button"
                                >
                                    Create User
                                </Button>
                            </DialogFooter>
                        </Form>
                    </DialogContent>
                </Dialog>
            </template>
        </PageHeader>

        <DataTableCard>
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
                        class="border-border/70 dark:border-border border-t"
                    >
                        <td class="p-4">{{ user.name }}</td>
                        <td class="p-4">{{ user.email }}</td>
                        <td class="p-4">
                            <div class="flex flex-wrap items-center gap-2">
                                <span>{{ roleLabel(user.role) }}</span>
                                <Badge
                                    v-if="user.artist_label"
                                    variant="secondary"
                                    :data-test="`user-${user.id}-artist-label`"
                                >
                                    {{ user.artist_label }}
                                </Badge>
                            </div>
                        </td>
                        <td class="p-4">
                            <div class="flex flex-wrap items-center gap-2">
                                <Badge
                                    v-if="!user.is_active"
                                    variant="destructive"
                                    >Deactivated</Badge
                                >
                                <Badge v-else variant="secondary">Active</Badge>
                                <Badge
                                    v-if="user.is_locked_out"
                                    variant="outline"
                                    class="border-destructive/40 text-destructive"
                                    :data-test="`user-${user.id}-locked-badge`"
                                >
                                    <Lock class="size-3" />
                                    Locked Out
                                </Badge>
                                <Badge
                                    v-if="user.artist_status"
                                    :variant="
                                        artistStatusBadgeVariant(
                                            user.artist_status,
                                        )
                                    "
                                    :class="
                                        artistStatusBadgeClass(
                                            user.artist_status,
                                        )
                                    "
                                >
                                    {{ artistStatusLabel(user.artist_status) }}
                                </Badge>
                                <Badge
                                    v-if="user.exceeded_break_time"
                                    variant="outline"
                                    class="text-muted-foreground"
                                >
                                    <Clock class="mr-1 size-3" />
                                    Exceeded break time
                                </Badge>
                            </div>
                        </td>
                        <td class="p-4 text-right">
                            <div
                                class="flex flex-wrap items-center justify-end gap-2"
                            >
                                <Button
                                    type="button"
                                    variant="outline"
                                    :data-test="`edit-user-${user.id}-button`"
                                    @click="openEditDialog(user)"
                                >
                                    <Pencil class="size-4" />
                                    Edit
                                </Button>
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
                                <Form
                                    v-else
                                    v-bind="
                                        UserManagementController.reactivate.form(
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
                                        variant="secondary"
                                        :disabled="processing"
                                        :data-test="`reactivate-user-${user.id}-button`"
                                    >
                                        Reactivate Account
                                    </Button>
                                </Form>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </DataTableCard>

        <Dialog v-model:open="editDialogOpen">
            <DialogContent v-if="editingUser">
                <Form
                    :key="editingUser.id"
                    v-bind="
                        UserManagementController.update.form(editingUser.id)
                    "
                    :options="{ preserveScroll: true }"
                    class="space-y-4"
                    v-slot="{ errors, processing }"
                    @success="editDialogOpen = false"
                >
                    <DialogHeader>
                        <DialogTitle>Edit {{ editingUser.name }}</DialogTitle>
                        <DialogDescription>
                            Changes take effect on their next page load and are
                            recorded in the audit trail.
                        </DialogDescription>
                    </DialogHeader>

                    <div class="grid gap-2">
                        <Label for="edit-user-name">Name</Label>
                        <Input
                            id="edit-user-name"
                            name="name"
                            autocomplete="off"
                            :default-value="editingUser.name"
                        />
                        <InputError :message="errors.name" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="edit-user-email">Email address</Label>
                        <Input
                            id="edit-user-email"
                            name="email"
                            type="email"
                            autocomplete="off"
                            :default-value="editingUser.email"
                        />
                        <InputError :message="errors.email" />
                    </div>

                    <input type="hidden" name="role" :value="editUserRole" />
                    <div class="grid gap-2">
                        <Label for="edit-user-role">Role</Label>
                        <Select
                            v-model="editUserRole"
                            :disabled="isEditingSelf"
                        >
                            <SelectTrigger
                                id="edit-user-role"
                                class="w-full"
                                data-test="edit-user-role"
                            >
                                <SelectValue placeholder="Choose a role" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="role in creatableRoles"
                                    :key="role"
                                    :value="role"
                                >
                                    {{ roleLabel(role) }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <p
                            v-if="isEditingSelf"
                            class="text-muted-foreground text-sm"
                        >
                            You can't change your own role — another Admin has
                            to do it, so the portal is never left without one.
                        </p>
                        <p
                            v-else-if="
                                editingUser.role === 'artist' &&
                                editUserRole !== 'artist'
                            "
                            class="text-muted-foreground text-sm"
                        >
                            They will give up {{ editingUser.artist_label }};
                            the next new artist can take that number.
                        </p>
                        <InputError :message="errors.role" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="edit-user-password">New Password</Label>
                        <Input
                            id="edit-user-password"
                            name="password"
                            type="password"
                            autocomplete="new-password"
                        />
                        <p class="text-muted-foreground text-sm">
                            Leave blank to keep current password.
                        </p>
                        <InputError :message="errors.password" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="edit-user-password-confirmation"
                            >Confirm New Password</Label
                        >
                        <Input
                            id="edit-user-password-confirmation"
                            name="password_confirmation"
                            type="password"
                            autocomplete="new-password"
                        />
                        <InputError :message="errors.password_confirmation" />
                    </div>

                    <DialogFooter class="gap-2">
                        <DialogClose as-child>
                            <Button type="button" variant="secondary"
                                >Cancel</Button
                            >
                        </DialogClose>
                        <Button
                            type="submit"
                            :disabled="processing"
                            data-test="update-user-button"
                        >
                            Save Changes
                        </Button>
                    </DialogFooter>
                </Form>
            </DialogContent>
        </Dialog>
    </PageContainer>
</template>

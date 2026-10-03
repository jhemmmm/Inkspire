<script setup lang="ts">
import { Form, Head, usePage } from '@inertiajs/vue3';
import { Clock, Lock, Pencil, Plus, UserX } from '@lucide/vue';
import { computed, ref } from 'vue';
import UserManagementController from '@/actions/App/Http/Controllers/Admin/UserManagementController';
import AvatarField from '@/components/AvatarField.vue';
import InputError from '@/components/InputError.vue';
import PageHeader from '@/components/PageHeader.vue';
import PageContainer from '@/components/PageContainer.vue';
import DataTableCard from '@/components/DataTableCard.vue';
import EmptyState from '@/components/EmptyState.vue';
import TableFilterBar from '@/components/TableFilterBar.vue';
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
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import StatusBadge from '@/components/StatusBadge.vue';
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
} from '@/components/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableEmpty,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useInitials } from '@/composables/useInitials';
import { useTableFilter } from '@/composables/useTableFilter';
import { adminNavItems } from '@/config/nav/admin';
import { artistStatusBadge, artistStatusLabel, roleLabel } from '@/lib/roles';
import { index as usersIndex } from '@/routes/admin/users';

interface ManagedUser {
    id: number;
    name: string;
    email: string;
    role: string;
    artist_label: string | null;
    avatar: string | null;
    is_active: boolean;
    is_locked_out: boolean;
    artist_status: string | null;
    exceeded_break_time: boolean;
}

const { getInitials } = useInitials();

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

const props = defineProps<{
    users: ManagedUser[];
}>();

/**
 * Controlled so a successful create can close it; left uncontrolled, the
 * dialog stayed open over the new row with the submitted values still in it.
 */
const createDialogOpen = ref(false);
const newUserRole = ref('');
const newUserAvatarName = ref('');

const page = usePage();
const currentUserId = computed(() => page.props.auth.user.id);

const ALL = 'all';

const STATUS_FILTER_VALUES = ['active', 'deactivated', 'locked'] as const;

/**
 * Seeds the Status filter from `?status=locked` on first load (D7) — the
 * Admin dashboard's "Locked-Out Accounts" tile links here with that query
 * param. `page.url` is the Inertia-shared current URL, available on both
 * server and client, so this needs no `window` access and is SSR-safe.
 * Any value outside the three known ones is ignored.
 */
function initialStatusFilter(): string {
    const status = new URLSearchParams(page.url.split('?')[1] ?? '').get(
        'status',
    );

    return status !== null &&
        (STATUS_FILTER_VALUES as readonly string[]).includes(status)
        ? status
        : ALL;
}

const roleFilter = ref(ALL);
const statusFilter = ref(initialStatusFilter());

const roleFilterOptions = [
    { value: ALL, label: 'All roles' },
    ...creatableRoles.map((role) => ({ value: role, label: roleLabel(role) })),
];

const statusFilterOptions = [
    { value: ALL, label: 'All statuses' },
    { value: 'active', label: 'Active' },
    { value: 'deactivated', label: 'Deactivated' },
    { value: 'locked', label: 'Locked out' },
];

const { searchTerm, filtered: filteredUsers } = useTableFilter(
    () => props.users,
    (user) => [user.name, user.email, user.artist_label, roleLabel(user.role)],
    {
        filters: [
            (user) =>
                roleFilter.value === ALL || user.role === roleFilter.value,
            (user) => {
                if (statusFilter.value === ALL) {
                    return true;
                }
                if (statusFilter.value === 'active') {
                    return user.is_active;
                }
                if (statusFilter.value === 'deactivated') {
                    return !user.is_active;
                }
                return user.is_locked_out;
            },
        ],
    },
);

const filtersActive = computed(
    () =>
        searchTerm.value.trim() !== '' ||
        roleFilter.value !== ALL ||
        statusFilter.value !== ALL,
);

function clearFilters(): void {
    searchTerm.value = '';
    roleFilter.value = ALL;
    statusFilter.value = ALL;
}

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
                <Dialog v-model:open="createDialogOpen">
                    <DialogTrigger as-child>
                        <Button
                            data-test="new-user-button"
                            @click="
                                newUserRole = '';
                                newUserAvatarName = '';
                            "
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
                            @success="createDialogOpen = false"
                        >
                            <DialogHeader>
                                <DialogTitle>Create a new user</DialogTitle>
                                <DialogDescription>
                                    They can log in immediately with the
                                    password you set below.
                                </DialogDescription>
                            </DialogHeader>

                            <AvatarField
                                id="create-user-avatar"
                                :name="newUserAvatarName"
                                :avatar-url="null"
                                :error="errors.avatar"
                            />

                            <div class="grid gap-2">
                                <Label for="create-user-name">Name</Label>
                                <Input
                                    id="create-user-name"
                                    name="name"
                                    autocomplete="name"
                                    placeholder="Full name"
                                    @input="
                                        newUserAvatarName = (
                                            $event.target as HTMLInputElement
                                        ).value
                                    "
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

        <TableFilterBar
            v-if="users.length > 0"
            v-model:search="searchTerm"
            search-label="Search users"
            search-placeholder="Name, email or role"
            :shown="filteredUsers.length"
            :total="users.length"
            :active="filtersActive"
            @clear="clearFilters"
        >
            <div class="flex min-w-0 flex-col gap-2 @lg:w-48">
                <Label for="user-role-filter">Role</Label>
                <Select v-model="roleFilter">
                    <SelectTrigger id="user-role-filter" class="w-full">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="option in roleFilterOptions"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>
            <div class="flex min-w-0 flex-col gap-2 @lg:w-48">
                <Label for="user-status-filter">Status</Label>
                <Select v-model="statusFilter">
                    <SelectTrigger id="user-status-filter" class="w-full">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="option in statusFilterOptions"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>
        </TableFilterBar>

        <DataTableCard>
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>User</TableHead>
                        <TableHead>Role</TableHead>
                        <TableHead>Status</TableHead>
                        <TableHead class="text-right">Actions</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableEmpty v-if="users.length === 0" :colspan="4">
                        <EmptyState
                            title="No users yet"
                            description="Staff accounts will appear here once created."
                        />
                    </TableEmpty>
                    <TableEmpty
                        v-else-if="filteredUsers.length === 0"
                        :colspan="4"
                    >
                        <EmptyState
                            title="No matches"
                            description="No users match that search, role or status filter."
                        >
                            <template #actions>
                                <Button
                                    type="button"
                                    variant="secondary"
                                    data-test="clear-user-filters-button"
                                    @click="clearFilters"
                                >
                                    Clear filters
                                </Button>
                            </template>
                        </EmptyState>
                    </TableEmpty>
                    <TableRow
                        v-for="user in filteredUsers"
                        v-else
                        :key="user.id"
                    >
                        <TableCell>
                            <div class="flex items-center gap-3">
                                <Avatar
                                    class="h-8 w-8 overflow-hidden rounded-full"
                                >
                                    <AvatarImage
                                        v-if="user.avatar"
                                        :src="user.avatar"
                                        :alt="user.name"
                                        class="object-cover"
                                    />
                                    <AvatarFallback
                                        class="from-ink-cyan to-primary text-primary-foreground bg-linear-to-br text-xs font-semibold"
                                    >
                                        {{ getInitials(user.name) }}
                                    </AvatarFallback>
                                </Avatar>
                                <!--
                                    Email sits under the name rather than in
                                    its own column: with both, the table was
                                    wider than its card on a 1280px laptop and
                                    Edit / Deactivate scrolled out of view.
                                -->
                                <div class="flex flex-col">
                                    <span class="font-medium">
                                        {{ user.name }}
                                    </span>
                                    <span class="text-muted-foreground text-sm">
                                        {{ user.email }}
                                    </span>
                                </div>
                            </div>
                        </TableCell>
                        <TableCell>
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
                        </TableCell>
                        <TableCell>
                            <div class="flex flex-wrap items-center gap-2">
                                <StatusBadge
                                    v-if="!user.is_active"
                                    tone="danger"
                                    >Deactivated</StatusBadge
                                >
                                <StatusBadge v-else tone="success"
                                    >Active</StatusBadge
                                >
                                <StatusBadge
                                    v-if="user.is_locked_out"
                                    tone="danger"
                                    :data-test="`user-${user.id}-locked-badge`"
                                >
                                    <Lock class="size-3" />
                                    Locked Out
                                </StatusBadge>
                                <StatusBadge
                                    v-if="user.artist_status"
                                    :tone="
                                        artistStatusBadge(user.artist_status)
                                    "
                                >
                                    {{ artistStatusLabel(user.artist_status) }}
                                </StatusBadge>
                                <Badge
                                    v-if="user.exceeded_break_time"
                                    variant="outline"
                                    class="text-muted-foreground"
                                >
                                    <Clock class="mr-1 size-3" />
                                    Exceeded break time
                                </Badge>
                            </div>
                        </TableCell>
                        <TableCell class="text-right">
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
                                            <UserX class="size-4" />
                                            Deactivate
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
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
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

                    <AvatarField
                        id="edit-user-avatar"
                        :name="editingUser.name"
                        :avatar-url="editingUser.avatar"
                        :error="errors.avatar"
                    />

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

<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import { ownerNavItems } from '@/config/nav/owner';
import { Input } from '@/components/ui/input';
import {
    Pagination,
    PaginationContent,
    PaginationEllipsis,
    PaginationFirst,
    PaginationItem,
    PaginationLast,
    PaginationNext,
    PaginationPrevious,
} from '@/components/ui/pagination';
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
import { index as auditTrailIndex } from '@/routes/owner/audit-trail';

interface AuditTrailUser {
    id: number;
    name: string;
    email: string;
    role: string;
}

interface AuditEntry {
    id: number;
    user_id: number | null;
    action: string;
    auditable_type: string | null;
    auditable_id: number | null;
    old_values: Record<string, unknown> | null;
    new_values: Record<string, unknown> | null;
    ip_address: string | null;
    created_at: string | null;
    user: AuditTrailUser | null;
}

/** Shape of Laravel's LengthAwarePaginator::toArray(), passed through as-is. */
interface PaginatedAuditEntries {
    data: AuditEntry[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}

interface AuditFilters {
    user?: string;
    action?: string;
    from?: string;
    to?: string;
}

const props = defineProps<{
    entries: PaginatedAuditEntries;
    filters: AuditFilters;
    users: { id: number; name: string }[];
    actions: string[];
}>();

defineOptions({
    layout: {
        navItems: ownerNavItems,
        breadcrumbs: [
            {
                title: 'Audit Trail',
                href: auditTrailIndex(),
            },
        ],
    },
});

const ALL = 'all';

const selectedUser = ref(props.filters.user ?? ALL);
const selectedAction = ref(props.filters.action ?? ALL);
const fromDate = ref(props.filters.from ?? '');
const toDate = ref(props.filters.to ?? '');

function actionLabel(action: string): string {
    return action
        .split('_')
        .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
        .join(' ');
}

function shortClassName(type: string | null): string {
    if (!type) {
        return '—';
    }

    const parts = type.split('\\');

    return parts[parts.length - 1];
}

function target(entry: AuditEntry): string {
    const className = shortClassName(entry.auditable_type);

    return entry.auditable_id ? `${className} #${entry.auditable_id}` : className;
}

function formattedTimestamp(entry: AuditEntry): string {
    return entry.created_at ? new Date(entry.created_at).toLocaleString() : '—';
}

function visit(page?: number): void {
    router.get(
        auditTrailIndex.url(),
        {
            ...(selectedUser.value !== ALL ? { user: selectedUser.value } : {}),
            ...(selectedAction.value !== ALL ? { action: selectedAction.value } : {}),
            ...(fromDate.value ? { from: fromDate.value } : {}),
            ...(toDate.value ? { to: toDate.value } : {}),
            ...(page ? { page } : {}),
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

function clearFilters(): void {
    selectedUser.value = ALL;
    selectedAction.value = ALL;
    fromDate.value = '';
    toDate.value = '';
    visit();
}
</script>

<template>
    <Head title="Audit Trail" />

    <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
        <h1 class="text-[28px] leading-[1.2] font-semibold">Audit Trail</h1>

        <form
            class="flex flex-wrap items-end gap-4"
            @submit.prevent="visit()"
        >
            <div class="flex flex-col gap-1">
                <label class="text-sm font-semibold" for="audit-filter-user">User</label>
                <Select v-model="selectedUser">
                    <SelectTrigger id="audit-filter-user" class="w-48">
                        <SelectValue placeholder="All users" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem :value="ALL">All users</SelectItem>
                        <SelectItem
                            v-for="user in users"
                            :key="user.id"
                            :value="String(user.id)"
                        >
                            {{ user.name }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>

            <div class="flex flex-col gap-1">
                <label class="text-sm font-semibold" for="audit-filter-action">Action</label>
                <Select v-model="selectedAction">
                    <SelectTrigger id="audit-filter-action" class="w-48">
                        <SelectValue placeholder="All actions" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem :value="ALL">All actions</SelectItem>
                        <SelectItem
                            v-for="action in actions"
                            :key="action"
                            :value="action"
                        >
                            {{ actionLabel(action) }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>

            <div class="flex flex-col gap-1">
                <label class="text-sm font-semibold" for="audit-filter-from">From</label>
                <Input id="audit-filter-from" v-model="fromDate" type="date" class="w-40" />
            </div>

            <div class="flex flex-col gap-1">
                <label class="text-sm font-semibold" for="audit-filter-to">To</label>
                <Input id="audit-filter-to" v-model="toDate" type="date" class="w-40" />
            </div>

            <Button type="submit" data-test="apply-audit-filters-button">
                Apply Filters
            </Button>
            <Button
                type="button"
                variant="secondary"
                data-test="clear-audit-filters-button"
                @click="clearFilters"
            >
                Clear
            </Button>
        </form>

        <div class="border-sidebar-border/70 dark:border-sidebar-border overflow-hidden rounded-xl border">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Timestamp</TableHead>
                        <TableHead>Actor</TableHead>
                        <TableHead>Role</TableHead>
                        <TableHead>Action</TableHead>
                        <TableHead>Target</TableHead>
                        <TableHead>IP Address</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableEmpty v-if="entries.data.length === 0" :colspan="6">
                        <div class="flex flex-col items-center gap-1 text-center">
                            <p class="font-semibold">No matching audit events</p>
                            <p class="text-muted-foreground">
                                No mutating actions or auth events match these
                                filters. Try a wider date range or clear the
                                user/action filters.
                            </p>
                        </div>
                    </TableEmpty>
                    <TableRow v-for="entry in entries.data" v-else :key="entry.id">
                        <TableCell>{{ formattedTimestamp(entry) }}</TableCell>
                        <TableCell>{{ entry.user?.name ?? '—' }}</TableCell>
                        <TableCell>{{ entry.user?.role ?? '—' }}</TableCell>
                        <TableCell>{{ actionLabel(entry.action) }}</TableCell>
                        <TableCell>{{ target(entry) }}</TableCell>
                        <TableCell>{{ entry.ip_address ?? '—' }}</TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>

        <Pagination
            v-if="entries.last_page > 1"
            v-slot="{ page }"
            :page="entries.current_page"
            :items-per-page="entries.per_page"
            :total="entries.total"
            :sibling-count="1"
            show-edges
            @update:page="visit"
        >
            <PaginationContent v-slot="{ items }">
                <PaginationFirst />
                <PaginationPrevious />

                <template v-for="(item, index) in items">
                    <PaginationItem
                        v-if="item.type === 'page'"
                        :key="index"
                        :value="item.value"
                        :is-active="item.value === page"
                    >
                        {{ item.value }}
                    </PaginationItem>
                    <PaginationEllipsis v-else :key="`ellipsis-${index}`" :index="index" />
                </template>

                <PaginationNext />
                <PaginationLast />
            </PaginationContent>
        </Pagination>
    </div>
</template>

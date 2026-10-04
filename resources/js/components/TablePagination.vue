<script setup lang="ts">
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

defineProps<{
    /** Laravel's LengthAwarePaginator::toArray(), passed through as-is. */
    paginator: {
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
}>();

const emit = defineEmits<{
    'update:page': [page: number];
}>();
</script>

<template>
    <Pagination
        v-if="paginator.last_page > 1"
        v-slot="{ page }"
        :page="paginator.current_page"
        :items-per-page="paginator.per_page"
        :total="paginator.total"
        :sibling-count="1"
        show-edges
        @update:page="emit('update:page', $event)"
    >
        <PaginationContent v-slot="{ items }" class="flex-wrap justify-center">
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
                <PaginationEllipsis
                    v-else
                    :key="`ellipsis-${index}`"
                    :index="index"
                />
            </template>

            <PaginationNext />
            <PaginationLast />
        </PaginationContent>
    </Pagination>
</template>

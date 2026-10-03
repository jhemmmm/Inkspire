<script setup lang="ts">
import { Search } from '@lucide/vue';
import { useId } from 'vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

withDefaults(
    defineProps<{
        searchPlaceholder?: string;
        searchLabel: string;
        shown: number;
        total: number;
        active: boolean;
        /**
         * No card of its own and a tighter layout, for a search that sits
         * beside another control (the production board's tabs) rather than
         * above a table. The count keeps its own line in both modes, so
         * Clear appearing never changes the bar's height.
         */
        inline?: boolean;
    }>(),
    {
        searchPlaceholder: 'Search…',
    },
);

const emit = defineEmits<{ clear: [] }>();

const search = defineModel<string>('search', { default: '' });

const searchInputId = useId();
</script>

<template>
    <component :is="inline ? 'div' : Card">
        <component
            :is="inline ? 'div' : CardContent"
            :class="['flex flex-col', inline ? 'gap-2' : 'gap-4']"
        >
            <div
                :class="
                    inline
                        ? 'flex items-center gap-3'
                        : 'flex flex-col gap-3 @lg:flex-row @lg:flex-wrap @lg:items-end'
                "
            >
                <div
                    :class="
                        inline
                            ? 'min-w-0 flex-1'
                            : 'flex w-full min-w-0 flex-col gap-2 @lg:max-w-sm @lg:flex-1'
                    "
                >
                    <Label :for="searchInputId" class="sr-only">
                        {{ searchLabel }}
                    </Label>
                    <div class="relative">
                        <Search
                            class="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2"
                        />
                        <Input
                            :id="searchInputId"
                            v-model="search"
                            type="search"
                            class="pl-9"
                            :placeholder="searchPlaceholder"
                            autocomplete="off"
                            data-test="table-filter-search-input"
                        />
                    </div>
                </div>

                <slot />

                <Button
                    v-if="active"
                    type="button"
                    variant="secondary"
                    :class="inline ? undefined : '@lg:ml-auto'"
                    data-test="table-filter-clear-button"
                    @click="emit('clear')"
                >
                    Clear filters
                </Button>
            </div>

            <p
                class="text-muted-foreground text-sm tabular-nums"
                aria-live="polite"
            >
                Showing {{ shown }} of {{ total }}
            </p>
        </component>
    </component>
</template>

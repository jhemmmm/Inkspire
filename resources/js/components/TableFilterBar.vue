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
    <Card>
        <CardContent class="flex flex-col gap-4">
            <div
                class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-end"
            >
                <div
                    class="flex w-full min-w-0 flex-col gap-2 sm:max-w-sm sm:flex-1"
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
                        />
                    </div>
                </div>

                <slot />

                <Button
                    v-if="active"
                    type="button"
                    variant="secondary"
                    class="sm:ml-auto"
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
        </CardContent>
    </Card>
</template>

<script setup lang="ts">
import { Check, ChevronDown, Search } from '@lucide/vue';
import {
    ComboboxAnchor,
    ComboboxContent,
    ComboboxEmpty,
    ComboboxInput,
    ComboboxItem,
    ComboboxItemIndicator,
    ComboboxPortal,
    ComboboxRoot,
    ComboboxTrigger,
    ComboboxViewport,
} from 'reka-ui';
import { computed, ref, watch } from 'vue';
import { cn } from '@/lib/utils';

export interface SearchableOption {
    value: string;
    label: string;
    /** Secondary line — a price, a dimension, whatever disambiguates. */
    hint?: string;
}

const props = withDefaults(
    defineProps<{
        options: SearchableOption[];
        id?: string;
        placeholder?: string;
        searchPlaceholder?: string;
        emptyText?: string;
        class?: string;
    }>(),
    {
        placeholder: 'Select an option',
        searchPlaceholder: 'Type to search…',
        emptyText: 'Nothing matches that search.',
    },
);

const emit = defineEmits<{
    /**
     * The typed term, for a list too long to ship whole: the page asks the
     * server for the matching options and passes them back in as `options`.
     */
    search: [term: string];
}>();

const model = defineModel<string>({ default: '' });

const search = ref('');
const isOpen = ref(false);

// Only while the menu is open: choosing an option closes it and writes that
// option's label into the input, which is not somebody searching.
watch(search, (term) => {
    if (isOpen.value) {
        emit('search', term.trim());
    }
});

const selected = computed(() =>
    props.options.find((option) => option.value === model.value),
);

/**
 * Filtering is done here rather than through the library's own matcher so the
 * rule is visible and testable: a case-insensitive substring match across both
 * the label and its hint, so "sintra" finds "Tarp on Sintraboard" and "1500"
 * finds the banner priced at that.
 */
/**
 * Take the selected label out of the search term whenever the menu opens.
 *
 * `displayValue` writes the selected label into the input, and the input *is*
 * the search term — so reopening a field that already had a value filtered the
 * list down to that one item, and typing appended to it ("Mug Printbacklit"),
 * leaving no way to change a selection without manually clearing the field.
 *
 * Only the label goes, not the whole term: typing into the closed field is
 * itself what opens the menu, and by the time this runs the keystroke is
 * already in the input beside the label. Clearing everything ate the first
 * letter of every search started from the keyboard.
 *
 * While the menu is open the current selection shows as the placeholder;
 * closing restores the label via `resetSearchTermOnBlur`.
 */
function onOpenChange(open: boolean): void {
    isOpen.value = open;

    if (open) {
        search.value = search.value.replace(selected.value?.label ?? '', '');
        // Even when the term did not change: a list the server narrowed for
        // an earlier search has to answer for this one before it is shown.
        emit('search', search.value.trim());
    }
}

const matches = computed(() => {
    const term = search.value.trim().toLowerCase();

    if (term === '') {
        return props.options;
    }

    return props.options.filter((option) =>
        `${option.label} ${option.hint ?? ''}`.toLowerCase().includes(term),
    );
});
</script>

<template>
    <!--
        `openOnClick` defaults to FALSE in reka-ui, so without it the menu
        only opens via the chevron or the keyboard — clicking the field
        appeared to do nothing. Not `openOnFocus`: a dialog that focused this
        field on open popped the list over the form before anyone touched it.
        Typing or ArrowDown still opens it from the keyboard.
    -->
    <ComboboxRoot
        v-model="model"
        :ignore-filter="true"
        :open-on-click="true"
        class="relative"
        @update:open="onOpenChange"
    >
        <ComboboxAnchor as-child>
            <!--
                Styled to match SelectTrigger exactly, so a searchable field
                and a plain one sitting in the same form look like the same
                control rather than two different widgets.
            -->
            <div
                :class="
                    cn(
                        'border-input bg-card focus-within:border-ring focus-within:ring-ring/50 dark:bg-input/30 flex h-9 w-full items-center gap-2 rounded-md border px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] focus-within:ring-[3px]',
                        props.class,
                    )
                "
            >
                <Search class="text-muted-foreground size-4 shrink-0" />
                <!--
                    The search term lives on the *input*, not the root:
                    ComboboxRoot emits only update:modelValue / update:open /
                    highlight, so binding a search term to it silently did
                    nothing and the filter never saw a keystroke.
                -->
                <ComboboxInput
                    :id="id"
                    v-model="search"
                    class="placeholder:text-muted-foreground min-w-0 flex-1 bg-transparent outline-none"
                    :placeholder="selected?.label ?? placeholder"
                    :display-value="() => selected?.label ?? ''"
                />
                <ComboboxTrigger class="shrink-0">
                    <ChevronDown class="size-4 opacity-50" />
                </ComboboxTrigger>
            </div>
        </ComboboxAnchor>

        <ComboboxPortal>
            <ComboboxContent
                position="popper"
                class="bg-popover text-popover-foreground data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 z-50 mt-1 max-h-72 w-(--reka-combobox-trigger-width) overflow-hidden rounded-md border shadow-md"
            >
                <ComboboxViewport class="max-h-72 overflow-y-auto p-1">
                    <ComboboxEmpty
                        class="text-muted-foreground px-2 py-6 text-center text-sm"
                    >
                        {{ emptyText }}
                    </ComboboxEmpty>

                    <ComboboxItem
                        v-for="option in matches"
                        :key="option.value"
                        :value="option.value"
                        class="data-[highlighted]:bg-accent data-[highlighted]:text-accent-foreground relative flex w-full cursor-default items-center gap-2 rounded-sm py-1.5 pr-8 pl-2 text-sm outline-hidden select-none"
                    >
                        <span class="flex min-w-0 flex-col gap-0.5">
                            <span class="truncate">{{ option.label }}</span>
                            <span
                                v-if="option.hint"
                                class="text-muted-foreground text-xs tabular-nums"
                            >
                                {{ option.hint }}
                            </span>
                        </span>
                        <ComboboxItemIndicator
                            class="absolute right-2 flex size-3.5 items-center justify-center"
                        >
                            <Check class="size-4" />
                        </ComboboxItemIndicator>
                    </ComboboxItem>
                </ComboboxViewport>
            </ComboboxContent>
        </ComboboxPortal>
    </ComboboxRoot>
</template>

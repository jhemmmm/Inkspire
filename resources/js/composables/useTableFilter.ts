import { computed, ref, toValue } from 'vue';
import type { ComputedRef, MaybeRefOrGetter, Ref } from 'vue';

/**
 * Case-insensitive substring match — the same rule `SearchableSelect.vue`
 * uses for its own option filtering. Exported standalone (not just used
 * internally) so the matching rule is checkable without a browser: this
 * project has no JS unit-test framework, so this is the one pure, directly
 * runnable piece of the filtering logic.
 */
export function matchesSearch(
    term: string,
    values: Array<string | null | undefined>,
): boolean {
    const normalized = term.trim().toLowerCase();

    if (normalized === '') {
        return true;
    }

    return values
        .filter(
            (value): value is string => value !== null && value !== undefined,
        )
        .join(' ')
        .toLowerCase()
        .includes(normalized);
}

/**
 * Client-side search + predicate filtering over a list already fully
 * loaded from the server. No debounce, no library — this is a small pure
 * computed over data that's already in memory.
 */
export function useTableFilter<T>(
    rows: MaybeRefOrGetter<T[]>,
    searchText: (row: T) => Array<string | null | undefined>,
    options?: {
        /** Extra predicates. Each reads the page's own filter refs, so it stays reactive. */
        filters?: Array<(row: T) => boolean>;
        /** Share one search box between two lists on the same page. */
        searchTerm?: Ref<string>;
    },
): { searchTerm: Ref<string>; filtered: ComputedRef<T[]> } {
    const searchTerm = options?.searchTerm ?? ref('');
    const predicates = options?.filters ?? [];

    const filtered = computed(() =>
        toValue(rows).filter(
            (row) =>
                matchesSearch(searchTerm.value, searchText(row)) &&
                predicates.every((predicate) => predicate(row)),
        ),
    );

    return { searchTerm, filtered };
}

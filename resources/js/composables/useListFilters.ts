import { router } from '@inertiajs/vue3';
import { reactive, watch } from 'vue';

/**
 * Reloads a list at `url`, keeping the page's state, such as what is typed
 * in its search, and replacing the history entry, so Back leaves the list
 * rather than stepping through its filters.
 */
export function reloadList(url: string): void {
    router.get(url, {}, { preserveState: true, replace: true });
}

/**
 * A list's filters, each a string, for its `FilterBar` to bind to: the
 * list reloads from its first page whenever one changes, with the filters
 * in its query string, leaving out any that is empty or "all". `onReload`
 * runs before each reload, for example to clear ticked rows.
 */
export function useListFilters<Filters extends Record<string, string>>(
    url: (options: { query: Record<string, string> }) => string,
    current: Filters,
    onReload?: () => void,
): Filters {
    const filters = reactive({ ...current }) as Filters;

    watch(filters, () => {
        onReload?.();
        reloadList(
            url({
                query: Object.fromEntries(
                    Object.entries(filters).filter(
                        ([, value]) => value !== '' && value !== 'all',
                    ),
                ),
            }),
        );
    });

    return filters;
}

import { router } from '@inertiajs/vue3';
import { reactive, watch } from 'vue';

/** Replaces the history entry, so Back leaves the list rather than stepping through its filters. */
export function reloadList(url: string): void {
    router.get(url, {}, { preserveState: true, replace: true });
}

export function useListFilters<Filters extends Record<string, string>>(
    url: (options: { query: Record<string, string> }) => string,
    current: Filters,
): Filters {
    const filters = reactive({ ...current }) as Filters;

    watch(filters, () => {
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

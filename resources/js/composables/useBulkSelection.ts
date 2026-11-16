import { router } from '@inertiajs/vue3';
import type { Ref } from 'vue';
import { computed, ref } from 'vue';

/**
 * The Work Entries ticked on a paginated list, kept across its pages: tick
 * one, a whole page, or every match (fetched as the page's `matchingIds`
 * prop), and clear them. Pair it with pagination links that preserve state,
 * or a page change remounts the list and drops the selection.
 */
export function useBulkSelection(
    /** The entries on the current page that may be selected. */
    selectableOnPage: () => { id: number }[],
    /** The page's `matchingIds` prop, sent only when asked for. */
    matchingIds: () => number[] | undefined,
): {
    selected: Ref<number[]>;
    isPageSelected: Ref<boolean>;
    isSelected: (id: number) => boolean;
    toggle: (id: number, checked: boolean) => void;
    togglePage: (checked: boolean) => void;
    selectAllMatching: () => void;
    clear: () => void;
} {
    const selected = ref<number[]>([]);

    const isPageSelected = computed(() => {
        const onPage = selectableOnPage();

        return (
            onPage.length > 0 &&
            onPage.every((entry) => selected.value.includes(entry.id))
        );
    });

    function isSelected(id: number): boolean {
        return selected.value.includes(id);
    }

    function toggle(id: number, checked: boolean): void {
        selected.value = checked
            ? [...selected.value.filter((selectedId) => selectedId !== id), id]
            : selected.value.filter((selectedId) => selectedId !== id);
    }

    function togglePage(checked: boolean): void {
        const onPage = selectableOnPage().map((entry) => entry.id);
        const others = selected.value.filter((id) => !onPage.includes(id));
        selected.value = checked ? [...others, ...onPage] : others;
    }

    function selectAllMatching(): void {
        router.reload({
            only: ['matchingIds'],
            onSuccess: () => {
                selected.value = [...(matchingIds() ?? [])];
            },
        });
    }

    function clear(): void {
        selected.value = [];
    }

    return {
        selected,
        isPageSelected,
        isSelected,
        toggle,
        togglePage,
        selectAllMatching,
        clear,
    };
}

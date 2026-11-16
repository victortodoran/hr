import { router } from '@inertiajs/vue3';
import type { Ref } from 'vue';
import { computed, ref } from 'vue';

/**
 * The rows ticked on a paginated list, kept across its pages: tick one, a
 * whole page, or every match (reloaded as an optional page prop listing
 * their IDs), and clear them. Pair it with pagination links that preserve
 * state, or a page change remounts the list and drops the selection.
 */
export function useBulkSelection(
    /** The rows on the current page that may be selected. */
    selectableOnPage: () => { id: number }[],
    /** The optional page prop with every matching row's ID, and its name. */
    matching: { prop: string; ids: () => number[] | undefined },
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
    const selectedSet = computed(() => new Set(selected.value));

    const isPageSelected = computed(() => {
        const onPage = selectableOnPage();

        return (
            onPage.length > 0 &&
            onPage.every((entry) => selectedSet.value.has(entry.id))
        );
    });

    function isSelected(id: number): boolean {
        return selectedSet.value.has(id);
    }

    function toggle(id: number, checked: boolean): void {
        selected.value = checked
            ? [...selected.value.filter((selectedId) => selectedId !== id), id]
            : selected.value.filter((selectedId) => selectedId !== id);
    }

    function togglePage(checked: boolean): void {
        const onPage = selectableOnPage().map((entry) => entry.id);
        const onPageSet = new Set(onPage);
        const others = selected.value.filter((id) => !onPageSet.has(id));
        selected.value = checked ? [...others, ...onPage] : others;
    }

    function selectAllMatching(): void {
        router.reload({
            only: [matching.prop],
            onSuccess: () => {
                selected.value = [...(matching.ids() ?? [])];
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

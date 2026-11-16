import { router } from '@inertiajs/vue3';
import type { Ref } from 'vue';
import { computed, ref } from 'vue';

/**
 * The rows ticked on a paginated list, kept across its pages: tick one, a
 * whole page, or every match (reloaded as an optional page prop listing
 * their IDs) on a paginated list, and clear them. Pair it with pagination links that preserve
 * state, or a page change remounts the list and drops the selection.
 * `submit` sends a bulk action for the selected rows, once at a time, and
 * then unticks the rows it sent.
 */
export function useBulkSelection(
    /** The rows on the current page that may be selected. */
    selectableOnPage: () => { id: number }[],
    /**
     * The optional page prop with every matching row's ID, and its name;
     * left out for a list shown whole, which has no other matches.
     */
    matching?: { prop: string; ids: () => number[] | undefined },
): {
    selected: Ref<number[]>;
    isPageSelected: Ref<boolean>;
    isSelected: (id: number) => boolean;
    toggle: (id: number, checked: boolean) => void;
    togglePage: (checked: boolean) => void;
    selectAllMatching: () => void;
    clear: () => void;
    processing: Ref<boolean>;
    submit: (url: string, onSuccess?: () => void) => void;
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
        if (!matching) {
            return;
        }

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

    /** Whether a bulk action is on its way, so it can't be sent twice. */
    const processing = ref(false);

    function submit(url: string, onSuccess?: () => void): void {
        if (processing.value) {
            return;
        }

        const ids = [...selected.value];
        const sent = new Set(ids);
        processing.value = true;
        router.post(
            url,
            { ids },
            {
                preserveScroll: true,
                onFinish: () => (processing.value = false),
                onSuccess: () => {
                    selected.value = selected.value.filter(
                        (id) => !sent.has(id),
                    );
                    onSuccess?.();
                },
            },
        );
    }

    return {
        selected,
        isPageSelected,
        isSelected,
        toggle,
        togglePage,
        selectAllMatching,
        clear,
        processing,
        submit,
    };
}

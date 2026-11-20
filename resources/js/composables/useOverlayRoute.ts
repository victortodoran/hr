import { router } from '@inertiajs/vue3';
import type { Ref } from 'vue';
import { ref, watch } from 'vue';

/**
 * A sheet with a URL of its own (rule L14): that URL renders the page
 * under it with a prop saying the sheet is open. The sheet opens and
 * closes with that prop, and closing it returns to the page's own URL,
 * keeping scroll and state.
 */
export function useOverlayRoute(
    isOpenInUrl: () => boolean,
    pageUrl: () => string,
): {
    /** Binds the sheet's `open`. */
    open: Ref<boolean>;
    /** Binds the sheet's `update:open`. */
    setOpen: (open: boolean) => void;
} {
    const open = ref(isOpenInUrl());

    watch(isOpenInUrl, (openInUrl) => {
        open.value = openInUrl;
    });

    function setOpen(next: boolean): void {
        open.value = next;

        if (!next && isOpenInUrl()) {
            router.get(
                pageUrl(),
                {},
                { preserveScroll: true, preserveState: true },
            );
        }
    }

    return { open, setOpen };
}

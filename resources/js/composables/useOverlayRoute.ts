import { router } from '@inertiajs/vue3';
import type { Ref } from 'vue';
import { ref, watch } from 'vue';

export function useOverlayRoute(
    isOpenInUrl: () => boolean,
    pageUrl: () => string,
): {
    open: Ref<boolean>;
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

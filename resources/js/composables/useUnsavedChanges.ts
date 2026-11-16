import { router } from '@inertiajs/vue3';
import type { Ref } from 'vue';
import { onBeforeUnmount, onMounted, ref } from 'vue';

/**
 * Asks before leaving a page with unsaved changes. Any Inertia visit that
 * would leave (a link, the sidebar, choosing another item) is held while
 * `dirty` is true and `pending` is set to it, so the page can ask; `discard`
 * then makes it, and `keep` drops it. Saving isn't held: only GET visits
 * leave. Reloading or closing the tab gets the browser's own prompt.
 * Browsers don't let pages stop the back button within the app.
 */
export function useUnsavedChanges(dirty: Ref<boolean>): {
    pending: Ref<string | null>;
    leave: (url: string) => void;
    discard: () => void;
    keep: () => void;
} {
    const pending = ref<string | null>(null);
    let leaving = false;

    const removeBefore = router.on('before', (event) => {
        const visit = event.detail.visit;

        if (!dirty.value || leaving || visit.method !== 'get') {
            return;
        }

        pending.value = visit.url.toString();
        event.preventDefault();
    });

    function onBeforeUnload(event: BeforeUnloadEvent): void {
        if (dirty.value && !leaving) {
            event.preventDefault();
        }
    }

    onMounted(() => window.addEventListener('beforeunload', onBeforeUnload));
    onBeforeUnmount(() => {
        removeBefore();
        window.removeEventListener('beforeunload', onBeforeUnload);
    });

    /** Leave for the URL, asking first when there are unsaved changes. */
    function leave(url: string): void {
        router.visit(url);
    }

    function discard(): void {
        const url = pending.value;
        pending.value = null;

        if (url !== null) {
            leaving = true;
            router.visit(url, {
                onFinish: () => {
                    leaving = false;
                },
            });
        }
    }

    function keep(): void {
        pending.value = null;
    }

    return { pending, leave, discard, keep };
}

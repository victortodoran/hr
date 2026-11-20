import { router } from '@inertiajs/vue3';
import type { Ref } from 'vue';
import { onBeforeUnmount, onMounted, ref } from 'vue';

/** Only GET visits are held, as saving and prefetching don't leave; browsers don't let pages stop the back button within the app. */
export function useUnsavedChanges(
    dirty: Ref<boolean>,
    onDiscard?: () => void,
): {
    pending: Ref<string | null>;
    leave: (url: string) => void;
    discard: () => void;
    keep: () => void;
} {
    const pending = ref<string | null>(null);
    let leaving = false;

    const removeBefore = router.on('before', (event) => {
        const visit = event.detail.visit;

        if (
            !dirty.value ||
            leaving ||
            visit.prefetch ||
            visit.method !== 'get'
        ) {
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

    function leave(url: string): void {
        router.visit(url);
    }

    function discard(): void {
        const url = pending.value;
        pending.value = null;

        if (url !== null) {
            leaving = true;
            router.visit(url, {
                onSuccess: () => onDiscard?.(),
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

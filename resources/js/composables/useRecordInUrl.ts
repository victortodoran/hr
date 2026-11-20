import { router, usePage } from '@inertiajs/vue3';
import type { ComputedRef, WritableComputedRef } from 'vue';
import { computed, ref, watch } from 'vue';

/** Kept in the URL so a reload or a shared link opens the RecordSheet again (rule K8). */
export function useRecordInUrl<Row extends { id: number }>(
    rows: () => Row[],
    {
        param = 'record',
        reload,
    }: {
        param?: string;
        reload?: string;
    } = {},
): {
    record: ComputedRef<Row | null>;
    recordOpen: WritableComputedRef<boolean>;
    openRecord: (id: number) => void;
    closeRecord: () => void;
} {
    const page = usePage();
    const asked = new URL(page.url, 'http://localhost').searchParams.get(param);
    const recordId = ref<number | null>(asked ? Number(asked) : null);
    const isLoading = ref(false);

    const record = computed(
        () => rows().find((row) => row.id === recordId.value) ?? null,
    );

    function urlShowing(id: number | null): URL {
        const url = new URL(window.location.href);

        if (id === null) {
            url.searchParams.delete(param);
        } else {
            url.searchParams.set(param, String(id));
        }

        return url;
    }

    function showInUrl(id: number | null): void {
        if (typeof window === 'undefined') {
            return;
        }

        window.history.replaceState(window.history.state, '', urlShowing(id));
    }

    function openRecord(id: number): void {
        recordId.value = id;

        if (reload === undefined) {
            showInUrl(id);

            return;
        }

        isLoading.value = true;
        router.get(
            urlShowing(id).href,
            {},
            {
                only: [reload],
                preserveState: true,
                preserveScroll: true,
                onFinish: () => {
                    isLoading.value = false;
                },
            },
        );
    }

    function closeRecord(): void {
        recordId.value = null;
        showInUrl(null);
    }

    watch(
        [record, isLoading],
        ([shown, loading]) => {
            if (shown === null && !loading && recordId.value !== null) {
                closeRecord();
            }
        },
        { immediate: true },
    );

    const recordOpen = computed({
        get: () => record.value !== null,
        set: (open: boolean) => {
            if (!open) {
                closeRecord();
            }
        },
    });

    return { record, recordOpen, openRecord, closeRecord };
}

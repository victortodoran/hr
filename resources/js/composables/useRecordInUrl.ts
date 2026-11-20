import { router, usePage } from '@inertiajs/vue3';
import type { ComputedRef, WritableComputedRef } from 'vue';
import { computed, ref, watch } from 'vue';

/**
 * Which row's RecordSheet is open, kept in the URL as `?record=` (or the
 * `param` given, such as `?person=`) so a reload or a shared link opens it
 * again (rule K8). By default the sheet is drawn from the list's own rows,
 * so changing it replaces the URL without a visit. With `reload`, the
 * sheet's record comes from the server instead: opening one visits the
 * URL reloading only that prop, and `rows` reads the record from it.
 * Once the row leaves the list (decided, on another page, or not sent)
 * the sheet closes and the parameter goes.
 */
export function useRecordInUrl<Row extends { id: number }>(
    rows: () => Row[],
    {
        param = 'record',
        reload,
    }: {
        /** The query parameter that names the open row. */
        param?: string;
        /** The prop the server fills with the open record, reloaded on open. */
        reload?: string;
    } = {},
): {
    /** The open row, or null while no sheet is open. */
    record: ComputedRef<Row | null>;
    /** Binds the RecordSheet's `open`; closing it clears the parameter. */
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

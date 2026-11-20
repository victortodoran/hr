import { usePage } from '@inertiajs/vue3';
import type { ComputedRef, WritableComputedRef } from 'vue';
import { computed, ref, watch } from 'vue';

/**
 * Which row's RecordSheet is open, kept in the URL as `?record=` so a
 * reload or a shared link opens it again (rule K8). The sheet is drawn
 * from the list's own rows, so changing it replaces the URL without a
 * visit; once the row leaves the list (decided, or on another page) the
 * sheet closes and `?record=` goes.
 */
export function useRecordInUrl<Row extends { id: number }>(
    rows: () => Row[],
): {
    /** The open row, or null while no sheet is open. */
    record: ComputedRef<Row | null>;
    /** Binds the RecordSheet's `open`; closing it clears `?record=`. */
    recordOpen: WritableComputedRef<boolean>;
    openRecord: (id: number) => void;
    closeRecord: () => void;
} {
    const page = usePage();
    const asked = new URL(page.url, 'http://localhost').searchParams.get(
        'record',
    );
    const recordId = ref<number | null>(asked ? Number(asked) : null);

    const record = computed(
        () => rows().find((row) => row.id === recordId.value) ?? null,
    );

    function showInUrl(id: number | null): void {
        if (typeof window === 'undefined') {
            return;
        }

        const url = new URL(window.location.href);

        if (id === null) {
            url.searchParams.delete('record');
        } else {
            url.searchParams.set('record', String(id));
        }

        window.history.replaceState(window.history.state, '', url);
    }

    function openRecord(id: number): void {
        recordId.value = id;
        showInUrl(id);
    }

    function closeRecord(): void {
        recordId.value = null;
        showInUrl(null);
    }

    watch(
        record,
        (shown) => {
            if (shown === null && recordId.value !== null) {
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

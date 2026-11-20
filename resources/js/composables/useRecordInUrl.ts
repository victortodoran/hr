import { usePage } from '@inertiajs/vue3';
import type { Ref } from 'vue';
import { ref } from 'vue';

/**
 * Which row's RecordSheet is open, kept in the URL as `?record=` so a
 * reload or a shared link opens it again (rule K8). The sheet is drawn
 * from the list's own rows, so changing it replaces the URL without a
 * visit.
 */
export function useRecordInUrl(): {
    recordId: Ref<number | null>;
    openRecord: (id: number) => void;
    closeRecord: () => void;
} {
    const page = usePage();
    const asked = new URL(page.url, 'http://localhost').searchParams.get(
        'record',
    );
    const recordId = ref<number | null>(asked ? Number(asked) : null);

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

    return { recordId, openRecord, closeRecord };
}

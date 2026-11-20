import type { RequestPayload } from '@inertiajs/core';
import { router } from '@inertiajs/vue3';
import type { Ref } from 'vue';
import { ref } from 'vue';
import { toast } from 'vue-sonner';

/**
 * Approves one record at once, without asking (rule X5), from its row or
 * its RecordSheet. `approving` holds the record being approved, so its
 * buttons spin and every Approve button waits until it's done (B6). A
 * refusal the server gives as `approval` shows as an error toast.
 */
export function useApproveOne(): {
    approving: Ref<number | null>;
    approveOne: (
        id: number,
        url: string,
        data: RequestPayload,
        onSuccess: () => void,
    ) => void;
} {
    const approving = ref<number | null>(null);

    function approveOne(
        id: number,
        url: string,
        data: RequestPayload,
        onSuccess: () => void,
    ): void {
        if (approving.value !== null) {
            return;
        }

        router.post(url, data, {
            preserveScroll: true,
            onStart: () => {
                approving.value = id;
            },
            onFinish: () => {
                approving.value = null;
            },
            onSuccess,
            onError: (errors) => {
                if (errors.approval) {
                    toast.error(errors.approval);
                }
            },
        });
    }

    return { approving, approveOne };
}

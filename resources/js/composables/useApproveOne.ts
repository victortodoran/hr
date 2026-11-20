import type { RequestPayload } from '@inertiajs/core';
import { router } from '@inertiajs/vue3';
import type { Ref } from 'vue';
import { ref } from 'vue';
import { toast } from 'vue-sonner';

export function useApproveOne(): {
    approving: Ref<number | null>;
    approveOne: (
        id: number,
        url: string,
        data: RequestPayload,
        onSuccess?: () => void,
    ) => void;
} {
    const approving = ref<number | null>(null);

    function approveOne(
        id: number,
        url: string,
        data: RequestPayload,
        onSuccess?: () => void,
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

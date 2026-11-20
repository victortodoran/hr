import { router } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';
import type { Ref } from 'vue';
import { onBeforeUnmount, ref, watch } from 'vue';

/** A refusal (403) also reloads the page, so it stops offering the change. */
export function useSaveOnPick<Value extends string | null>(options: {
    saved: () => Value;
    url: () => string;
    field: string;
    label: string;
}): {
    chosen: Ref<Value>;
    saving: Ref<boolean>;
    justSaved: Ref<boolean>;
    save: (value: Value) => void;
} {
    const chosen = ref(options.saved()) as Ref<Value>;
    const saving = ref(false);
    const justSaved = ref(false);

    watch(options.saved, (value) => (chosen.value = value));

    let hideSavedTick: ReturnType<typeof setTimeout> | undefined;

    onBeforeUnmount(() => clearTimeout(hideSavedTick));

    function save(value: Value): void {
        if (value === options.saved()) {
            return;
        }

        chosen.value = value;

        const refuse = (message: string): void => {
            chosen.value = options.saved();

            toast.error(message);
        };

        router.put(
            options.url(),
            { [options.field]: value },
            {
                preserveScroll: true,
                preserveState: true,
                onStart: () => {
                    saving.value = true;
                    justSaved.value = false;
                },
                onFinish: () => (saving.value = false),
                onSuccess: () => {
                    justSaved.value = true;
                    clearTimeout(hideSavedTick);
                    hideSavedTick = setTimeout(
                        () => (justSaved.value = false),
                        2000,
                    );
                },
                onError: (errors) =>
                    refuse(
                        errors[options.field] ??
                            `The ${options.label} wasn’t saved.`,
                    ),
                onHttpException: (response) => {
                    if (response.status !== 403) {
                        refuse(`The ${options.label} wasn’t saved. Try again.`);

                        return false;
                    }

                    refuse(
                        `The ${options.label} wasn’t saved: you can’t change it any more, as the Performance Evaluation may have been Completed.`,
                    );
                    router.reload();

                    return false;
                },
                onNetworkError: () => {
                    refuse(
                        `The ${options.label} wasn’t saved: the connection failed. Try again.`,
                    );

                    return false;
                },
            },
        );
    }

    return { chosen, saving, justSaved, save };
}

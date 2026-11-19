import { router } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';
import type { Ref } from 'vue';
import { onBeforeUnmount, ref, watch } from 'vue';

/**
 * Saves a dropdown's value as soon as it is picked, or a text box's when
 * `save` is called as it loses focus, sending it as `field` in a PUT to
 * `url`. `chosen` is what the dropdown shows: the saved value
 * until another is picked, and the saved one again whenever the page's
 * props change it. A save keeps the page's scroll and state, and
 * `justSaved` turns true for two seconds after it. When saving fails,
 * for example because the Performance Evaluation was Completed
 * meanwhile, the dropdown goes back to the saved value and an error
 * toast says why, unless `keepsChosenOnFailure` keeps what was typed so
 * it isn't lost; a refusal (403) also reloads the page, so it stops
 * offering the change.
 */
export function useSaveOnPick<Value extends string | null>(options: {
    /** The value as saved, from the page's props. */
    saved: () => Value;
    url: () => string;
    /** The name the server takes the value under. */
    field: string;
    /** What the value is, as the error toasts name it, e.g. "Progress". */
    label: string;
    /** Whether a failed save leaves `chosen` as it is, for text that took typing, rather than putting the saved value back. */
    keepsChosenOnFailure?: boolean;
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
            if (!options.keepsChosenOnFailure) {
                chosen.value = options.saved();
            }

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

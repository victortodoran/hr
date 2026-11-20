<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import type { RouteFormDefinition } from '@/wayfinder';

/**
 * Asks before a step that changes something's state without deleting it,
 * such as reopening or cancelling, in plain colours; deletes ask through
 * `ConfirmDeleteDialog` instead. The `trigger` slot is the button that opens
 * it, and the default slot holds any fields sent along, such as the IDs of
 * the selected rows. With `withoutTrigger` it has no trigger and opens
 * through `v-model:open`, such as from a row's ⋯ menu. The dialog closes and emits `success` once the request
 * succeeds, and shows the refusal when it doesn't.
 */
withDefaults(
    defineProps<{
        /** Where the request asked about is sent, from a Wayfinder `.form()`. */
        form: RouteFormDefinition<'post'>;
        title: string;
        description: string;
        /** The button that goes ahead. */
        confirmLabel: string;
        /** The button that closes the dialog without going ahead. */
        dismissLabel?: string;
        /** Leaves out the trigger, for a dialog opened through `v-model:open`. */
        withoutTrigger?: boolean;
    }>(),
    { dismissLabel: 'Cancel', withoutTrigger: false },
);

const emit = defineEmits<{
    success: [];
}>();

const open = defineModel<boolean>('open', { default: false });

function succeeded(): void {
    open.value = false;
    emit('success');
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogTrigger v-if="!withoutTrigger" as-child>
            <slot name="trigger" />
        </DialogTrigger>
        <DialogContent>
            <Form
                v-bind="form"
                :options="{ preserveScroll: true }"
                class="space-y-6"
                @success="succeeded"
                v-slot="{ errors, processing }"
            >
                <slot />

                <DialogHeader class="space-y-3">
                    <DialogTitle>{{ title }}</DialogTitle>
                    <DialogDescription>{{ description }}</DialogDescription>
                </DialogHeader>

                <InputError :message="Object.values(errors).find(Boolean)" />

                <DialogFooter class="gap-2">
                    <DialogClose as-child>
                        <Button type="button" variant="secondary">{{
                            dismissLabel
                        }}</Button>
                    </DialogClose>
                    <SubmitButton :processing="processing">{{
                        confirmLabel
                    }}</SubmitButton>
                </DialogFooter>
            </Form>
        </DialogContent>
    </Dialog>
</template>

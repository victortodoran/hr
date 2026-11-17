<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { ref } from 'vue';
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
 * Asks before deleting something, the one such question across the app,
 * also used before withdrawing something for good, such as cancelling a
 * Travel Request. The `trigger` slot is the button that opens it, a plain
 * "Delete" unless given; the dialog closes once the request succeeds, and
 * shows the refusal when it doesn't.
 */
withDefaults(
    defineProps<{
        /** Where the delete, or other request asked about, is sent, from a Wayfinder `.form()`. */
        form: RouteFormDefinition<'post'>;
        title: string;
        description: string;
        /** Keeps this dialog's errors apart when several share a page. */
        errorBag?: string;
        /** The button that goes ahead. */
        confirmLabel?: string;
        /** The button that closes the dialog without going ahead. */
        dismissLabel?: string;
    }>(),
    { errorBag: undefined, confirmLabel: 'Delete', dismissLabel: 'Cancel' },
);

const open = ref(false);
</script>

<template>
    <Dialog v-model:open="open">
        <DialogTrigger as-child>
            <slot name="trigger">
                <Button variant="ghost" size="sm" class="text-destructive"
                    >Delete</Button
                >
            </slot>
        </DialogTrigger>
        <DialogContent>
            <Form
                v-bind="form"
                :error-bag="errorBag"
                :options="{ preserveScroll: true }"
                class="space-y-6"
                @success="open = false"
                v-slot="{ errors, processing }"
            >
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
                    <SubmitButton
                        :processing="processing"
                        variant="destructive"
                        >{{ confirmLabel }}</SubmitButton
                    >
                </DialogFooter>
            </Form>
        </DialogContent>
    </Dialog>
</template>

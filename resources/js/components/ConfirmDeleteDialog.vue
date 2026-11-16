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
 * Asks before deleting something, the one such question across the app.
 * The `trigger` slot is the button that opens it; the dialog closes once
 * the delete succeeds, and shows the refusal under `errorKey` when it doesn't.
 */
defineProps<{
    /** Where the delete is sent, from a Wayfinder `.form()`. */
    form: RouteFormDefinition<'post'>;
    title: string;
    description: string;
    /** The validation key a refusal comes back under, if one can. */
    errorKey?: string;
    /** Keeps this dialog's errors apart when several share a page. */
    errorBag?: string;
}>();

const open = ref(false);
</script>

<template>
    <Dialog v-model:open="open">
        <DialogTrigger as-child>
            <slot name="trigger" />
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

                <InputError
                    v-if="errorKey"
                    :message="(errors as Record<string, string>)[errorKey]"
                />

                <DialogFooter class="gap-2">
                    <DialogClose as-child>
                        <Button type="button" variant="secondary"
                            >Cancel</Button
                        >
                    </DialogClose>
                    <SubmitButton :processing="processing" variant="destructive"
                        >Delete</SubmitButton
                    >
                </DialogFooter>
            </Form>
        </DialogContent>
    </Dialog>
</template>

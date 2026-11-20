<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { RouteFormDefinition } from '@/wayfinder';

/**
 * Asks for a name, the one such dialog across the app: renaming something
 * by its name alone, opened by a "Rename…" button and closed once the new
 * name is saved, or adding a record by its name, given a `title` and a
 * `submitLabel`. Opened from elsewhere, such as a ⋯ menu item, it takes
 * `v-model:open` and `without-trigger` instead.
 */
withDefaults(
    defineProps<{
        /** Where the new name is sent, from a Wayfinder `.form()`. */
        form: RouteFormDefinition<'post'>;
        /** The name as it stands, which the field starts from. */
        name: string;
        /** The name field's id, unique on the page. */
        fieldId: string;
        /** The dialog's title; "Rename <name>" unless given. */
        title?: string;
        /** The button that saves; "Save" unless given. */
        submitLabel?: string;
        /** Leaves out the trigger, for a dialog opened through `v-model:open`. */
        withoutTrigger?: boolean;
    }>(),
    {
        title: undefined,
        submitLabel: 'Save',
        withoutTrigger: false,
    },
);

const open = defineModel<boolean>('open', { default: false });
</script>

<template>
    <Dialog v-model:open="open">
        <DialogTrigger v-if="!withoutTrigger" as-child>
            <Button variant="secondary" size="sm">Rename…</Button>
        </DialogTrigger>
        <DialogContent>
            <Form
                v-bind="form"
                :options="{ preserveScroll: true }"
                class="space-y-6"
                @success="open = false"
                v-slot="{ errors, processing }"
            >
                <DialogHeader>
                    <DialogTitle>{{ title ?? `Rename ${name}` }}</DialogTitle>
                </DialogHeader>

                <div class="grid gap-2">
                    <Label :for="fieldId">Name</Label>
                    <Input
                        :id="fieldId"
                        name="name"
                        :default-value="name"
                        required
                    />
                    <InputError :message="errors.name" />
                </div>

                <DialogFooter class="gap-2">
                    <DialogClose as-child>
                        <Button type="button" variant="secondary"
                            >Cancel</Button
                        >
                    </DialogClose>
                    <SubmitButton :processing="processing">{{
                        submitLabel
                    }}</SubmitButton>
                </DialogFooter>
            </Form>
        </DialogContent>
    </Dialog>
</template>

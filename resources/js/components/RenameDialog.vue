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
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { RouteFormDefinition } from '@/wayfinder';

/**
 * Renames something by its name alone, the one such dialog across the app,
 * opened by a "Rename" button and closed once the new name is saved.
 */
defineProps<{
    /** Where the new name is sent, from a Wayfinder `.form()`. */
    form: RouteFormDefinition<'post'>;
    /** The name as it stands, which the field starts from. */
    name: string;
    /** The name field's id, unique on the page. */
    fieldId: string;
}>();

const open = ref(false);
</script>

<template>
    <Dialog v-model:open="open">
        <DialogTrigger as-child>
            <Button variant="secondary" size="sm">Rename</Button>
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
                    <DialogTitle>Rename {{ name }}</DialogTitle>
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
                    <SubmitButton :processing="processing">Save</SubmitButton>
                </DialogFooter>
            </Form>
        </DialogContent>
    </Dialog>
</template>

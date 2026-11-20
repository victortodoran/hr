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

withDefaults(
    defineProps<{
        form: RouteFormDefinition<'post'>;
        title: string;
        description: string;
        confirmLabel: string;
        dismissLabel?: string;
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

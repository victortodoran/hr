<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { ref } from 'vue';
import WorkEntryController from '@/actions/App/Http/Controllers/WorkEntryController';
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
import type { WorkEntryRecord } from '@/types';

defineProps<{
    entry: WorkEntryRecord;
}>();

const open = ref(false);
</script>

<template>
    <Dialog v-model:open="open">
        <DialogTrigger as-child>
            <Button
                type="button"
                variant="ghost"
                size="sm"
                class="text-destructive"
                >Delete</Button
            >
        </DialogTrigger>
        <DialogContent>
            <Form
                v-bind="WorkEntryController.destroy.form(entry.id)"
                :options="{ preserveScroll: true }"
                class="space-y-6"
                @success="open = false"
                v-slot="{ processing }"
            >
                <DialogHeader class="space-y-3">
                    <DialogTitle>Delete this Work Entry?</DialogTitle>
                    <DialogDescription>
                        {{ entry.hours }} h on {{ entry.project }} ·
                        {{ entry.description }}. This can't be undone.
                    </DialogDescription>
                </DialogHeader>

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

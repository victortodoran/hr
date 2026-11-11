<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { ref } from 'vue';
import ClientController from '@/actions/App/Http/Controllers/ClientController';
import InputError from '@/components/InputError.vue';
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
import type { ClientRecord } from '@/types';

defineProps<{
    client: ClientRecord;
}>();

const open = ref(false);
</script>

<template>
    <Dialog v-model:open="open">
        <DialogTrigger as-child>
            <Button variant="ghost" size="sm" class="text-destructive"
                >Delete</Button
            >
        </DialogTrigger>
        <DialogContent>
            <Form
                v-bind="ClientController.destroy.form(client)"
                :options="{ preserveScroll: true }"
                class="space-y-6"
                @success="open = false"
                v-slot="{ errors, processing }"
            >
                <DialogHeader class="space-y-3">
                    <DialogTitle>Delete {{ client.name }}?</DialogTitle>
                    <DialogDescription>
                        Only a Client with no Projects can be deleted.
                    </DialogDescription>
                </DialogHeader>

                <InputError :message="errors.client" />

                <DialogFooter class="gap-2">
                    <DialogClose as-child>
                        <Button variant="secondary">Cancel</Button>
                    </DialogClose>
                    <Button
                        type="submit"
                        variant="destructive"
                        :disabled="processing"
                    >
                        Delete
                    </Button>
                </DialogFooter>
            </Form>
        </DialogContent>
    </Dialog>
</template>

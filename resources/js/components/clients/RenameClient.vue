<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { ref } from 'vue';
import ClientController from '@/actions/App/Http/Controllers/ClientController';
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
import type { ClientRecord } from '@/types';

defineProps<{
    client: ClientRecord;
}>();

const open = ref(false);
</script>

<template>
    <Dialog v-model:open="open">
        <DialogTrigger as-child>
            <Button variant="outline" size="sm">Rename</Button>
        </DialogTrigger>
        <DialogContent>
            <Form
                v-bind="ClientController.update.form(client)"
                :options="{ preserveScroll: true }"
                class="space-y-6"
                @success="open = false"
                v-slot="{ errors, processing }"
            >
                <DialogHeader>
                    <DialogTitle>Rename {{ client.name }}</DialogTitle>
                </DialogHeader>

                <div class="grid gap-2">
                    <Label :for="`client-name-${client.id}`">Name</Label>
                    <Input
                        :id="`client-name-${client.id}`"
                        name="name"
                        :default-value="client.name"
                        required
                    />
                    <InputError :message="errors.name" />
                </div>

                <DialogFooter class="gap-2">
                    <DialogClose as-child>
                        <Button variant="secondary">Cancel</Button>
                    </DialogClose>
                    <SubmitButton :processing="processing">Save</SubmitButton>
                </DialogFooter>
            </Form>
        </DialogContent>
    </Dialog>
</template>

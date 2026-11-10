<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { ref } from 'vue';
import RoleController from '@/actions/App/Http/Controllers/RoleController';
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
import type { RoleRecord } from '@/types';

defineProps<{
    role: RoleRecord;
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
                v-bind="RoleController.destroy.form(role.id)"
                :error-bag="`delete-role-${role.id}`"
                :options="{ preserveScroll: true }"
                class="space-y-6"
                @success="open = false"
                v-slot="{ errors, processing }"
            >
                <DialogHeader class="space-y-3">
                    <DialogTitle>Delete {{ role.name }}?</DialogTitle>
                    <DialogDescription>
                        Everyone who holds it loses what it allows.
                    </DialogDescription>
                </DialogHeader>

                <InputError :message="errors.role" />

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

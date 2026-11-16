<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import RoleController from '@/actions/App/Http/Controllers/RoleController';
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
import type { RoleRecord } from '@/types';

defineProps<{
    role: RoleRecord;
}>();

const open = ref(false);
</script>

<template>
    <Dialog v-model:open="open">
        <DialogTrigger as-child>
            <Button
                variant="ghost"
                size="icon"
                class="size-11 text-muted-foreground hover:bg-destructive/10 hover:text-destructive"
                :aria-label="`Delete ${role.name}`"
            >
                <Trash2 class="size-4" aria-hidden="true" />
            </Button>
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
                    <SubmitButton :processing="processing" variant="destructive"
                        >Delete</SubmitButton
                    >
                </DialogFooter>
            </Form>
        </DialogContent>
    </Dialog>
</template>

<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { ref } from 'vue';
import DepartmentController from '@/actions/App/Http/Controllers/DepartmentController';
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
import type { Department } from '@/types';

defineProps<{
    department: Department;
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
                v-bind="DepartmentController.destroy.form(department)"
                :options="{ preserveScroll: true }"
                class="space-y-6"
                @success="open = false"
                v-slot="{ errors, processing }"
            >
                <DialogHeader class="space-y-3">
                    <DialogTitle>Delete {{ department.name }}?</DialogTitle>
                    <DialogDescription>
                        Only a Department with no Employees can be deleted.
                    </DialogDescription>
                </DialogHeader>

                <InputError :message="errors.department" />

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

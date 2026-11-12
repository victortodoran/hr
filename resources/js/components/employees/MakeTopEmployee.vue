<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { ref } from 'vue';
import TopEmployeeController from '@/actions/App/Http/Controllers/TopEmployeeController';
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
import type { EmployeeRecord } from '@/types';

defineProps<{
    employee: EmployeeRecord;
}>();

const open = ref(false);
</script>

<template>
    <Dialog v-model:open="open">
        <DialogTrigger as-child>
            <Button variant="outline" size="sm">Make top Employee</Button>
        </DialogTrigger>
        <DialogContent>
            <Form
                v-bind="TopEmployeeController.store.form(employee.id)"
                :options="{ preserveScroll: true }"
                class="space-y-6"
                @success="open = false"
                v-slot="{ errors, processing }"
            >
                <DialogHeader class="space-y-3">
                    <DialogTitle
                        >Make {{ employee.name }} the top Employee?</DialogTitle
                    >
                    <DialogDescription>
                        {{ employee.name }} will have no Manager, and the
                        current top Employee will report to them.
                    </DialogDescription>
                </DialogHeader>

                <InputError :message="errors.employee" />

                <DialogFooter class="gap-2">
                    <DialogClose as-child>
                        <Button variant="secondary">Cancel</Button>
                    </DialogClose>
                    <SubmitButton :processing="processing"
                        >Make top Employee</SubmitButton
                    >
                </DialogFooter>
            </Form>
        </DialogContent>
    </Dialog>
</template>

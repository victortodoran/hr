<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { ref } from 'vue';
import PaidDaysController from '@/actions/App/Http/Controllers/TimeOff/PaidDaysController';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { Choice } from '@/types';

/**
 * Edits an Employee's Paid Days per year in a small dialog, opened by an
 * "Edit…" button (B8) and closed once saved. It warns that the number counts for
 * every year, past ones included.
 */
defineProps<{
    employee: Choice;
    /** The Employee's Paid Days, the same number for every year. */
    paidDays: number;
}>();

const open = ref(false);
</script>

<template>
    <Dialog v-model:open="open">
        <DialogTrigger as-child>
            <Button
                variant="secondary"
                size="sm"
                aria-label="Edit Paid Days per year…"
                >Edit…</Button
            >
        </DialogTrigger>
        <DialogContent>
            <Form
                v-bind="PaidDaysController.store.form(employee.id)"
                :options="{ preserveScroll: true }"
                class="space-y-6"
                @success="open = false"
                v-slot="{ errors, processing }"
            >
                <DialogHeader>
                    <DialogTitle>Edit Paid Days per year</DialogTitle>
                    <DialogDescription
                        >Days of Paid Time Off {{ employee.name }} gets each
                        year. This also changes past years, and the Paid Days
                        left in them.</DialogDescription
                    >
                </DialogHeader>

                <div class="grid gap-2">
                    <Label for="paid-days">Paid Days per year</Label>
                    <Input
                        id="paid-days"
                        name="days"
                        type="number"
                        min="0"
                        max="366"
                        step="1"
                        class="w-24"
                        :default-value="paidDays"
                        required
                    />
                    <InputError :message="errors.days" />
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

<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import PaidDaysController from '@/actions/App/Http/Controllers/TimeOff/PaidDaysController';
import InputError from '@/components/InputError.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { Choice } from '@/types';

defineProps<{
    employee: Choice;
    /** The Employee's Paid Days, the same number for every year. */
    paidDays: number;
}>();
</script>

<template>
    <Form
        v-bind="PaidDaysController.store.form(employee.id)"
        :options="{ preserveScroll: true }"
        class="grid gap-2"
        v-slot="{ errors, processing }"
    >
        <Label for="paid-days">Paid Days per year</Label>
        <div class="flex items-center gap-2">
            <Input
                id="paid-days"
                :key="paidDays"
                name="days"
                type="number"
                min="0"
                max="366"
                step="1"
                class="w-24"
                :default-value="paidDays"
                required
            />
            <SubmitButton :processing="processing" variant="outline"
                >Save</SubmitButton
            >
        </div>
        <InputError :message="errors.days" />
    </Form>
</template>

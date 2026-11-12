<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import PaidDaysController from '@/actions/App/Http/Controllers/PaidDaysController';
import InputError from '@/components/InputError.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { Choice, PaidDaysRecord } from '@/types';

const props = defineProps<{
    employee: Choice;
    paidDays: PaidDaysRecord;
}>();

const fieldId = `paid-days-${props.paidDays.year}`;
</script>

<template>
    <Form
        v-bind="PaidDaysController.store.form(employee.id)"
        :options="{ preserveScroll: true }"
        class="grid gap-2"
        v-slot="{ errors, processing }"
    >
        <input type="hidden" name="year" :value="paidDays.year" />
        <Label :for="fieldId">{{ paidDays.year }}</Label>
        <div class="flex items-center gap-2">
            <Input
                :id="fieldId"
                :key="paidDays.days"
                name="days"
                type="number"
                min="0"
                max="366"
                step="1"
                class="w-24"
                :default-value="paidDays.days"
                required
            />
            <SubmitButton :processing="processing" variant="outline"
                >Save</SubmitButton
            >
        </div>
        <InputError :message="errors.days ?? errors.year" />
    </Form>
</template>

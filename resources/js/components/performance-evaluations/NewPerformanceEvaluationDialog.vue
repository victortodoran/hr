<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import PerformanceEvaluationController from '@/actions/App/Http/Controllers/Performance/PerformanceEvaluationController';
import InputError from '@/components/InputError.vue';
import SegmentedControl from '@/components/SegmentedControl.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { CreatableYear } from '@/types';

const props = defineProps<{
    creatableYears: CreatableYear[];
}>();

const open = defineModel<boolean>('open', { required: true });

const form = useForm({
    year: props.creatableYears[0]?.year ?? new Date().getFullYear(),
    employee_id: '',
});

watch(open, (isOpen) => {
    if (isOpen) {
        form.year = props.creatableYears[0]?.year ?? form.year;
        form.employee_id = '';
        form.clearErrors();
    }
});

const yearOptions = computed(() =>
    props.creatableYears.map((option) => ({
        value: String(option.year),
        label: String(option.year),
    })),
);

const employees = computed(
    () =>
        props.creatableYears.find((option) => option.year === form.year)
            ?.employees ?? [],
);

function chooseYear(year: string): void {
    form.year = Number(year);
    form.employee_id = '';
    form.clearErrors();
}

function createEvaluation(): void {
    form.submit(PerformanceEvaluationController.store());
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent>
            <form class="space-y-6" @submit.prevent="createEvaluation">
                <DialogHeader>
                    <DialogTitle>New Performance Evaluation</DialogTitle>
                    <DialogDescription
                        >It's Open as soon as it's made, so you can add Goals
                        right away.</DialogDescription
                    >
                </DialogHeader>

                <div class="grid justify-items-start gap-2">
                    <span class="text-meta font-medium" aria-hidden="true"
                        >Year</span
                    >
                    <SegmentedControl
                        :model-value="String(form.year)"
                        :options="yearOptions"
                        label="Year"
                        fill-on-phone
                        @update:model-value="chooseYear"
                    />
                    <InputError :message="form.errors.year" />
                </div>

                <div class="grid gap-2">
                    <Label for="evaluation-employee">Employee</Label>
                    <Select
                        name="employee_id"
                        required
                        :disabled="employees.length === 0"
                        :model-value="form.employee_id"
                        @update:model-value="
                            form.employee_id = $event ? String($event) : ''
                        "
                    >
                        <SelectTrigger
                            id="evaluation-employee"
                            class="w-full max-sm:h-11"
                            aria-describedby="evaluation-employee-help"
                        >
                            <SelectValue
                                :placeholder="
                                    employees.length === 0
                                        ? `Everyone already has a ${form.year} Performance Evaluation`
                                        : 'Choose an Employee'
                                "
                            />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="employee in employees"
                                :key="employee.id"
                                :value="String(employee.id)"
                                >{{ employee.name }}</SelectItem
                            >
                        </SelectContent>
                    </Select>
                    <p
                        id="evaluation-employee-help"
                        class="text-caption text-muted-foreground"
                    >
                        Only the people you're the Manager of who don't have a
                        {{ form.year }} Performance Evaluation yet.
                    </p>
                    <InputError :message="form.errors.employee_id" />
                </div>

                <DialogFooter class="gap-2">
                    <Button
                        type="button"
                        variant="secondary"
                        @click="open = false"
                        >Cancel</Button
                    >
                    <SubmitButton :processing="form.processing"
                        >New Performance Evaluation</SubmitButton
                    >
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>

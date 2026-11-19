<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import PerformanceEvaluationController from '@/actions/App/Http/Controllers/Performance/PerformanceEvaluationController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import PageContainer from '@/components/PageContainer.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardFooter } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { create, index } from '@/routes/performance-evaluations';
import type { CreatableYear } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Performance Evaluations', href: index() },
            { title: 'New Evaluation', href: create() },
        ],
    },
});

/**
 * Creating a Performance Evaluation for the current or the next year, for
 * one of the Employees the viewer manages who has none for it yet. It is
 * Open at once, and creating it opens its page.
 */
const props = defineProps<{
    /** The current year, then the next, each with who may get one. */
    years: CreatableYear[];
}>();

const form = useForm({
    year: props.years[0].year,
    employee_id: '',
});

const employees = computed(
    () =>
        props.years.find((option) => option.year === form.year)?.employees ??
        [],
);

/**
 * Choose the year, dropping an Employee chosen for the other one.
 */
function chooseYear(year: number): void {
    form.year = year;
    form.employee_id = '';
    form.clearErrors();
}

function createEvaluation(): void {
    form.submit(PerformanceEvaluationController.store());
}
</script>

<template>
    <Head title="New Performance Evaluation" />

    <PageContainer width="narrow">
        <Heading
            title="New Performance Evaluation"
            description="It's Open as soon as you create it, so you can start giving Goals right away."
        />

        <form @submit.prevent="createEvaluation">
            <Card>
                <CardContent class="space-y-6">
                    <fieldset class="grid gap-2">
                        <legend class="mb-2 text-sm font-medium">Year</legend>
                        <div
                            class="inline-flex w-fit rounded-md border bg-muted p-0.5"
                        >
                            <label
                                v-for="option in years"
                                :key="option.year"
                                class="cursor-pointer rounded px-4 py-1.5 text-sm font-medium has-[:checked]:bg-background has-[:checked]:shadow-sm has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-ring"
                                :class="
                                    form.year === option.year
                                        ? 'text-foreground'
                                        : 'text-muted-foreground'
                                "
                            >
                                <input
                                    type="radio"
                                    name="year"
                                    class="sr-only"
                                    :value="option.year"
                                    :checked="form.year === option.year"
                                    @change="chooseYear(option.year)"
                                />
                                {{ option.year }}
                            </label>
                        </div>
                        <InputError :message="form.errors.year" />
                    </fieldset>

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
                                class="w-full"
                            >
                                <SelectValue
                                    :placeholder="
                                        employees.length === 0
                                            ? `Everyone already has a ${form.year} Evaluation`
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
                        <p class="text-sm text-muted-foreground">
                            Only the people you're the Manager of who don't have
                            a
                            {{ form.year }} Evaluation yet.
                        </p>
                        <InputError :message="form.errors.employee_id" />
                    </div>
                </CardContent>
                <CardFooter class="gap-2 border-t">
                    <SubmitButton :processing="form.processing"
                        >Create Evaluation</SubmitButton
                    >
                    <Button variant="ghost" as-child>
                        <Link :href="index()">Cancel</Link>
                    </Button>
                </CardFooter>
            </Card>
        </form>
    </PageContainer>
</template>

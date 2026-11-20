<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { watch } from 'vue';
import GoalController from '@/actions/App/Http/Controllers/Performance/GoalController';
import FormSheet from '@/components/FormSheet.vue';
import GoalFields from '@/components/performance-evaluations/GoalFields.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import { Button } from '@/components/ui/button';
import type { Goal } from '@/types';

/**
 * Adding a Goal to a Performance Evaluation, or editing one, in a sheet
 * over the page it was opened from (rule L14): its title and what
 * achieving it looks like. Adding submits as "Add Goal", the button that
 * opened it (C13); editing saves the Goal as "Save Goal". Each opening
 * starts from the Goal as saved, or blank.
 */
const props = defineProps<{
    performanceEvaluationId: number;
    /** The Goal being edited; none while adding one. */
    goal?: Goal | null;
    /** One line under the title: whose Performance Evaluation and which year. */
    description: string;
}>();

const open = defineModel<boolean>('open', { required: true });

const FORM_ID = 'goal-form';

const form = useForm({
    title: props.goal?.title ?? '',
    description: props.goal?.description ?? '',
});

watch(
    open,
    (isOpen) => {
        if (isOpen) {
            form.defaults({
                title: props.goal?.title ?? '',
                description: props.goal?.description ?? '',
            });
            form.reset();
            form.clearErrors();
        }
    },
    { immediate: true },
);

function submit(): void {
    const options = { preserveScroll: true };

    if (props.goal) {
        form.submit(
            GoalController.update({
                performanceEvaluation: props.performanceEvaluationId,
                goal: props.goal.id,
            }),
            options,
        );

        return;
    }

    form.submit(GoalController.store(props.performanceEvaluationId), options);
}
</script>

<template>
    <FormSheet
        v-model:open="open"
        :title="goal ? 'Edit Goal' : 'Add Goal'"
        :description="description"
    >
        <form :id="FORM_ID" @submit.prevent="submit">
            <GoalFields
                v-model:title="form.title"
                v-model:description="form.description"
                :errors="form.errors"
            />
        </form>

        <template #footer>
            <Button type="button" variant="ghost" @click="open = false"
                >Cancel</Button
            >
            <SubmitButton :form="FORM_ID" :processing="form.processing">{{
                goal ? 'Save Goal' : 'Add Goal'
            }}</SubmitButton>
        </template>
    </FormSheet>
</template>

<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import GoalController from '@/actions/App/Http/Controllers/Performance/GoalController';
import InputError from '@/components/InputError.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { show } from '@/routes/performance-evaluations';
import type { Goal } from '@/types';

/**
 * Gives a Performance Evaluation a Goal, or, given one, changes it. Saving
 * and cancelling both lead back to the Performance Evaluation's page.
 */
const props = defineProps<{
    /** The Performance Evaluation the Goal is on. */
    evaluationId: number;
    /** The Goal to change; none to give a new one. */
    goal?: Goal;
}>();

const form = useForm({
    title: props.goal?.title ?? '',
    description: props.goal?.description ?? '',
});

function save(): void {
    if (props.goal) {
        form.submit(
            GoalController.update({
                performanceEvaluation: props.evaluationId,
                goal: props.goal.id,
            }),
        );

        return;
    }

    form.submit(GoalController.store(props.evaluationId));
}
</script>

<template>
    <form class="space-y-6" @submit.prevent="save">
        <div class="grid gap-2">
            <Label for="goal-title">Title</Label>
            <Input
                id="goal-title"
                v-model="form.title"
                placeholder="Lead the Relaunch API work"
                maxlength="255"
                required
            />
            <InputError :message="form.errors.title" />
        </div>
        <div class="grid gap-2">
            <Label for="goal-description">Description</Label>
            <Input
                id="goal-description"
                v-model="form.description"
                placeholder="What achieving it looks like by the end of the year"
                maxlength="5000"
                required
            />
            <InputError :message="form.errors.description" />
        </div>
        <div class="flex gap-2">
            <SubmitButton :processing="form.processing">{{
                goal ? 'Save Goal' : 'Give Goal'
            }}</SubmitButton>
            <Button variant="ghost" as-child>
                <Link :href="show(evaluationId)">Cancel</Link>
            </Button>
        </div>
    </form>
</template>

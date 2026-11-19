<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import { Lightbulb } from '@lucide/vue';
import GoalController from '@/actions/App/Http/Controllers/Performance/GoalController';
import InputError from '@/components/InputError.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardFooter } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { show } from '@/routes/performance-evaluations';
import { show as showGoal } from '@/routes/performance-evaluations/goals';
import type { Goal } from '@/types';

/**
 * Gives a Performance Evaluation a Goal, or, given one, changes it. Saving
 * and cancelling lead back to the Performance Evaluation's page, or to the
 * Goal's own page when it was changed from there.
 */
const props = defineProps<{
    /** The Performance Evaluation the Goal is on. */
    evaluationId: number;
    /** The Goal to change; none to give a new one. */
    goal?: Goal;
    /** Whether saving and cancelling lead back to the Goal's own page. */
    returnsToGoal?: boolean;
}>();

const form = useForm({
    title: props.goal?.title ?? '',
    description: props.goal?.description ?? '',
    from: props.returnsToGoal ? 'goal' : null,
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
    <form @submit.prevent="save">
        <Card>
            <CardContent class="space-y-6">
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
                    <Label for="goal-description"
                        >What achieving it looks like</Label
                    >
                    <Textarea
                        id="goal-description"
                        v-model="form.description"
                        class="min-h-36"
                        maxlength="5000"
                        required
                    />
                    <p
                        class="flex items-start gap-2 text-sm text-muted-foreground"
                    >
                        <Lightbulb class="mt-0.5 size-4 shrink-0" />
                        Say what done looks like by the end of the year, so you
                        can both tell whether it was Met.
                    </p>
                    <InputError :message="form.errors.description" />
                </div>
            </CardContent>
            <CardFooter class="gap-2 border-t">
                <SubmitButton :processing="form.processing">{{
                    goal ? 'Save Goal' : 'Give Goal'
                }}</SubmitButton>
                <Button variant="ghost" as-child>
                    <Link
                        :href="
                            returnsToGoal && goal
                                ? showGoal({
                                      performanceEvaluation: evaluationId,
                                      goal: goal.id,
                                  })
                                : show(evaluationId)
                        "
                        >Cancel</Link
                    >
                </Button>
            </CardFooter>
        </Card>
    </form>
</template>

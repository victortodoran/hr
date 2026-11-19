<script setup lang="ts">
import { Head, setLayoutProps } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import PageContainer from '@/components/PageContainer.vue';
import GoalForm from '@/components/performance-evaluations/GoalForm.vue';
import { index, show } from '@/routes/performance-evaluations';
import { edit, show as showGoal } from '@/routes/performance-evaluations/goals';
import type { Goal, PerformanceEvaluationSummary } from '@/types';

const props = defineProps<{
    evaluation: PerformanceEvaluationSummary;
    goal: Goal;
    /** Whether it was opened from the Goal's own page, where saving leads back. */
    returnsToGoal: boolean;
}>();

const title = `Change ${props.goal.title}`;

setLayoutProps({
    breadcrumbs: [
        {
            title: 'Performance Evaluations',
            href: index({ query: { year: String(props.evaluation.year) } }),
        },
        {
            title: `${props.evaluation.employee.name} · ${props.evaluation.year}`,
            href: show(props.evaluation.id),
        },
        ...(props.returnsToGoal
            ? [
                  {
                      title: props.goal.title,
                      href: showGoal({
                          performanceEvaluation: props.evaluation.id,
                          goal: props.goal.id,
                      }),
                  },
              ]
            : []),
        {
            title: 'Change Goal',
            href: edit(
                {
                    performanceEvaluation: props.evaluation.id,
                    goal: props.goal.id,
                },
                props.returnsToGoal ? { query: { from: 'goal' } } : {},
            ),
        },
    ],
});
</script>

<template>
    <Head :title="title" />

    <PageContainer width="narrow">
        <Heading
            :title="title"
            :description="`For the ${evaluation.year} Performance Evaluation.`"
        />

        <GoalForm
            :evaluation-id="evaluation.id"
            :goal="goal"
            :returns-to-goal="returnsToGoal"
        />
    </PageContainer>
</template>

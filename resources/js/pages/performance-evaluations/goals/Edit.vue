<script setup lang="ts">
import { Head, setLayoutProps } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import PageContainer from '@/components/PageContainer.vue';
import GoalForm from '@/components/performance-evaluations/GoalForm.vue';
import { index, show } from '@/routes/performance-evaluations';
import { edit } from '@/routes/performance-evaluations/goals';
import type { Goal, PerformanceEvaluationName } from '@/types';

const props = defineProps<{
    evaluation: PerformanceEvaluationName;
    goal: Goal;
}>();

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
        {
            title: 'Change Goal',
            href: edit({
                performanceEvaluation: props.evaluation.id,
                goal: props.goal.id,
            }),
        },
    ],
});
</script>

<template>
    <Head title="Change Goal" />

    <PageContainer width="narrow">
        <Heading
            title="Change Goal"
            :description="`${evaluation.employee.name} · ${evaluation.year} Performance Evaluation`"
        />

        <GoalForm :evaluation-id="evaluation.id" :goal="goal" />
    </PageContainer>
</template>

<script setup lang="ts">
import { Head, setLayoutProps } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import PageContainer from '@/components/PageContainer.vue';
import GoalForm from '@/components/performance-evaluations/GoalForm.vue';
import { index, show } from '@/routes/performance-evaluations';
import { create } from '@/routes/performance-evaluations/goals';
import type { PerformanceEvaluationName } from '@/types';

const props = defineProps<{
    evaluation: PerformanceEvaluationName;
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
        { title: 'Give a Goal', href: create(props.evaluation.id) },
    ],
});
</script>

<template>
    <Head title="Give a Goal" />

    <PageContainer width="narrow">
        <Heading
            title="Give a Goal"
            :description="`${evaluation.employee.name} · ${evaluation.year} Performance Evaluation`"
        />

        <GoalForm :evaluation-id="evaluation.id" />
    </PageContainer>
</template>

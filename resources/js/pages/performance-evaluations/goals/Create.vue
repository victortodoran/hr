<script setup lang="ts">
import { Head, setLayoutProps } from '@inertiajs/vue3';
import PageHeader from '@/components/PageHeader.vue';
import PageContainer from '@/components/PageContainer.vue';
import GoalForm from '@/components/performance-evaluations/GoalForm.vue';
import { index, show } from '@/routes/performance-evaluations';
import { create } from '@/routes/performance-evaluations/goals';
import type { PerformanceEvaluationSummary } from '@/types';

const props = defineProps<{
    evaluation: PerformanceEvaluationSummary;
}>();

/** The Employee's first name, taken as the first word of their name. */
const firstName = props.evaluation.employee.name.trim().split(/\s+/)[0];
const title = `Give ${firstName} a Goal`;

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
    <Head :title="title" />

    <PageContainer width="form">
        <PageHeader
            :title="title"
            :description="`For the ${evaluation.year} Performance Evaluation. ${firstName} sees it as soon as you give it.`"
        />

        <GoalForm :evaluation-id="evaluation.id" />
    </PageContainer>
</template>

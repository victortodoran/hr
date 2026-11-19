<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Target } from '@lucide/vue';
import DataTable from '@/components/DataTable.vue';
import EmptyState from '@/components/EmptyState.vue';
import Heading from '@/components/Heading.vue';
import PageContainer from '@/components/PageContainer.vue';
import NoGoalsYet from '@/components/performance-evaluations/NoGoalsYet.vue';
import PerformanceEvaluationBody from '@/components/performance-evaluations/PerformanceEvaluationBody.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { formatDate } from '@/lib/dates';
import { outcomes } from '@/lib/outcomes';
import { index } from '@/routes/my-performance-evaluations';
import { show } from '@/routes/performance-evaluations';
import type {
    OwnPerformanceEvaluation,
    PerformanceEvaluationView,
    TableColumn,
} from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Performance', href: index() }],
    },
});

/**
 * The Employee's own Performance Evaluation for this year on top, shown
 * as its own page shows it: its Goals as cards, whose Progress they change
 * while it is Open, under its Outcome and summary once Completed. While
 * there is none, the top says the Manager hasn't set the year's Goals yet.
 * Their other years are listed below, each linking to its own page.
 */
const props = defineProps<{
    /** This calendar year. */
    year: number;
    /** The Employee's Performance Evaluation for this year, or null while they have none. */
    current: PerformanceEvaluationView | null;
    /** Their Performance Evaluations for every other year, newest first. */
    evaluations: OwnPerformanceEvaluation[];
}>();

const columns: TableColumn[] = [
    { key: 'year', label: 'Year' },
    { key: 'goals_count', label: 'Goals' },
    { key: 'state', label: 'Status', align: 'end' },
];

/** "Past years", or "Other years" once next year's is listed too. */
const otherYearsTitle = computed(() =>
    props.evaluations.some((evaluation) => evaluation.year > props.year)
        ? 'Other years'
        : 'Past years',
);
</script>

<template>
    <Head title="Performance" />

    <PageContainer width="wide">
        <Heading
            title="Performance"
            description="The Goals your Manager gives you for each year"
        />

        <section class="flex flex-col gap-4" aria-labelledby="current-year">
            <div
                class="flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-muted-foreground"
            >
                <h2
                    id="current-year"
                    class="text-lg font-semibold text-foreground"
                >
                    {{ year }}
                </h2>
                <template v-if="current">
                    <StatusBadge :state="current.evaluation.state" />
                    <span v-if="current.evaluation.completed_at"
                        >on
                        {{ formatDate(current.evaluation.completed_at) }}</span
                    >
                </template>
            </div>

            <PerformanceEvaluationBody
                v-if="current"
                :evaluation="current.evaluation"
                :is-own="current.isOwn"
                :can-edit="current.canEdit"
                :can-change-progress="current.canChangeProgress"
                :can-see-outcomes="current.canSeeOutcomes"
            />
            <NoGoalsYet v-else
                >Your Manager hasn't set your {{ year }} Goals yet. They appear
                here once they do.</NoGoalsYet
            >
        </section>

        <section class="flex flex-col gap-4" aria-labelledby="other-years">
            <h2 id="other-years" class="text-lg font-semibold">
                {{ otherYearsTitle }}
            </h2>

            <DataTable :columns="columns" :rows="evaluations">
                <template #cell-year="{ row }">
                    <Link
                        :href="show(row.id)"
                        class="font-medium underline-offset-4 hover:underline"
                        >{{ row.year }}</Link
                    >
                </template>
                <template #cell-state="{ row }">
                    <div class="flex items-center justify-end gap-2">
                        <span
                            v-if="row.outcome"
                            class="text-muted-foreground"
                            >{{ outcomes[row.outcome] }}</span
                        >
                        <StatusBadge :state="row.state" />
                        <StatusBadge
                            v-if="row.at_risk_goals_count > 0"
                            state="at-risk"
                            >{{ row.at_risk_goals_count }} At Risk</StatusBadge
                        >
                    </div>
                </template>
                <template #empty>
                    <EmptyState
                        :icon="Target"
                        message="Your Performance Evaluations for other years appear here."
                    />
                </template>
            </DataTable>
        </section>
    </PageContainer>
</template>

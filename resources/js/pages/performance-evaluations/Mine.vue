<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { Target } from '@lucide/vue';
import DataTable from '@/components/DataTable.vue';
import EmptyState from '@/components/EmptyState.vue';
import FactList from '@/components/FactList.vue';
import PageHeader from '@/components/PageHeader.vue';
import ScopeSwitch from '@/components/ScopeSwitch.vue';
import PageContainer from '@/components/PageContainer.vue';
import NoGoalsYet from '@/components/performance-evaluations/NoGoalsYet.vue';
import PerformanceEvaluationBody from '@/components/performance-evaluations/PerformanceEvaluationBody.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import TextLink from '@/components/TextLink.vue';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { firstName } from '@/composables/useInitials';
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

const props = defineProps<{
    year: number;
    current: PerformanceEvaluationView | null;
    evaluations: OwnPerformanceEvaluation[];
}>();

const columns: TableColumn[] = [
    { key: 'year', label: 'Year' },
    { key: 'goals_count', label: 'Goals', align: 'end' },
    { key: 'state', label: 'Status', align: 'end' },
];

const allYears = computed(() =>
    [props.year, ...props.evaluations.map((evaluation) => evaluation.year)]
        .sort((a, b) => b - a)
        .map(String),
);

const usesYearTabs = computed(() => allYears.value.length > 3);

const selectedYear = ref(String(props.year));

const selectedOther = computed(() =>
    props.evaluations.find(
        (evaluation) => String(evaluation.year) === selectedYear.value,
    ),
);

const otherYearsTitle = computed(() =>
    props.evaluations.some((evaluation) => evaluation.year > props.year)
        ? 'Other years'
        : 'Past years',
);
</script>

<template>
    <Head title="Performance" />

    <PageContainer width="full">
        <PageHeader
            title="Performance"
            description="The Goals your Manager gives you for each year"
        />
        <ScopeSwitch area="performance" />

        <Tabs v-if="usesYearTabs" v-model="selectedYear">
            <TabsList aria-label="Year">
                <TabsTrigger
                    v-for="shown in allYears"
                    :key="shown"
                    :value="shown"
                    class="tabular-nums"
                    >{{ shown }}</TabsTrigger
                >
            </TabsList>
        </Tabs>

        <section
            v-if="!usesYearTabs || selectedYear === String(year)"
            class="flex flex-col gap-4"
            aria-labelledby="current-year"
        >
            <div
                class="flex flex-wrap items-center gap-x-2 gap-y-1 text-body text-muted-foreground"
            >
                <h2 id="current-year" class="text-section text-foreground">
                    {{ year }}
                </h2>
                <template v-if="current">
                    <StatusBadge :state="current.evaluation.state" />
                    <span v-if="current.evaluation.completed_at"
                        >Completed on
                        <span class="tabular-nums">{{
                            formatDate(current.evaluation.completed_at)
                        }}</span></span
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
                :is-assessing="false"
                :employee-first-name="
                    firstName(current.evaluation.employee.name)
                "
            />
            <NoGoalsYet v-else
                >Your Manager hasn't added your {{ year }} Goals yet. They
                appear here once they do.</NoGoalsYet
            >
        </section>

        <section
            v-else-if="selectedOther"
            class="flex flex-col gap-4"
            aria-labelledby="other-year"
        >
            <h2 id="other-year" class="text-section">
                {{ selectedOther.year }}
            </h2>
            <FactList
                :facts="[
                    { label: 'Status', value: selectedOther.state },
                    {
                        label: 'Goals',
                        value: String(selectedOther.goals_count),
                    },
                    {
                        label: 'Outcome',
                        value: selectedOther.outcome
                            ? outcomes[selectedOther.outcome]
                            : null,
                    },
                ]"
            >
                <template #fact-Status>
                    <div class="flex flex-wrap items-center gap-2">
                        <StatusBadge :state="selectedOther.state" />
                        <StatusBadge
                            v-if="selectedOther.at_risk_goals_count > 0"
                            state="at-risk"
                            >{{ selectedOther.at_risk_goals_count }} At
                            Risk</StatusBadge
                        >
                    </div>
                </template>
            </FactList>
            <p class="text-body">
                <TextLink :href="show(selectedOther.id)"
                    >{{ selectedOther.year }} Performance Evaluation</TextLink
                >
            </p>
        </section>

        <section
            v-if="!usesYearTabs"
            class="flex flex-col gap-4"
            aria-labelledby="other-years"
        >
            <h2 id="other-years" class="text-section">
                {{ otherYearsTitle }}
            </h2>

            <DataTable :columns="columns" :rows="evaluations">
                <template #cell-year="{ row }">
                    <Link
                        :href="show(row.id)"
                        class="font-medium tabular-nums underline-offset-4 hover:underline"
                        >{{ row.year }}</Link
                    >
                </template>
                <template #cell-goals_count="{ row }">
                    <span class="tabular-nums">{{ row.goals_count }}</span>
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

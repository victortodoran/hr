<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Plus, Target } from '@lucide/vue';
import DataTable from '@/components/DataTable.vue';
import EmptyState from '@/components/EmptyState.vue';
import FilterBar from '@/components/FilterBar.vue';
import FilterSearch from '@/components/FilterSearch.vue';
import FilterSelect from '@/components/FilterSelect.vue';
import Heading from '@/components/Heading.vue';
import PageContainer from '@/components/PageContainer.vue';
import Pagination from '@/components/Pagination.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { useListFilters } from '@/composables/useListFilters';
import { outcomes } from '@/lib/outcomes';
import { create, index, show } from '@/routes/performance-evaluations';
import type {
    Choice,
    FilterChoice,
    Paginated,
    PerformanceEvaluationRow,
    PerformanceEvaluationState,
    TableColumn,
} from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Performance Evaluations', href: index() }],
    },
});

/**
 * For one year, the Employees the viewer effectively manages or may read
 * the Performance Evaluations of who have one for it, each with that
 * Performance Evaluation; New Evaluation starts one for those still
 * without. An Employee's page links here narrowed to them, which the bar
 * shows as an Employee to clear.
 */
const props = defineProps<{
    year: number;
    years: number[];
    search: string;
    state: PerformanceEvaluationState | null;
    /** The one Employee the list is narrowed to, null when it isn't. */
    employee: Choice | null;
    canCreate: boolean;
    employees: Paginated<PerformanceEvaluationRow>;
}>();

const columns: TableColumn[] = [
    { key: 'name', label: 'Employee' },
    { key: 'goals', label: 'Goals', minor: true },
    { key: 'state', label: 'Status', align: 'end' },
];

const yearChoices = props.years.map((option) => ({
    value: String(option),
    label: String(option),
}));

const states: FilterChoice[] = [
    { value: 'open', label: 'Open' },
    { value: 'completed', label: 'Completed' },
];

const employeeChoices: FilterChoice[] = props.employee
    ? [{ value: String(props.employee.id), label: props.employee.name }]
    : [];

const filters = useListFilters(index.url, {
    year: String(props.year),
    search: props.search,
    employee: props.employee ? String(props.employee.id) : 'all',
    state: props.state ?? 'all',
});
</script>

<template>
    <Head title="Performance Evaluations" />

    <PageContainer width="wide">
        <Heading
            title="Performance Evaluations"
            description="The Evaluations you write for the Employees you are the Manager of, and those you may read"
        >
            <template #actions>
                <Button v-if="canCreate" as-child>
                    <Link :href="create()">
                        <Plus class="size-4" aria-hidden="true" />
                        New Evaluation
                    </Link>
                </Button>
            </template>
        </Heading>

        <FilterBar>
            <FilterSelect
                v-model="filters.year"
                label="Year"
                width="w-32"
                :choices="yearChoices"
            />
            <FilterSearch v-model="filters.search" />
            <FilterSelect
                v-if="employee"
                v-model="filters.employee"
                label="Employee"
                any="Any Employee"
                width="w-48"
                :choices="employeeChoices"
            />
            <FilterSelect
                v-model="filters.state"
                label="State"
                any="Any state"
                :choices="states"
            />
        </FilterBar>

        <DataTable :columns="columns" :rows="employees.data">
            <template #cell-name="{ row }">
                <Link
                    :href="show(row.evaluation.id)"
                    class="font-medium underline-offset-4 hover:underline"
                    >{{ row.name }}</Link
                >
            </template>
            <template #cell-goals="{ row }">
                {{ row.evaluation.goals_count }}
            </template>
            <template #cell-state="{ row }">
                <div
                    class="flex items-center justify-end gap-2 whitespace-nowrap"
                >
                    <span
                        v-if="row.evaluation.outcome"
                        class="text-muted-foreground"
                        >{{ outcomes[row.evaluation.outcome] }}</span
                    >
                    <StatusBadge :state="row.evaluation.state" />
                    <StatusBadge
                        v-if="row.evaluation.at_risk_goals_count > 0"
                        state="at-risk"
                        >{{ row.evaluation.at_risk_goals_count }} At
                        Risk</StatusBadge
                    >
                </div>
            </template>
            <template #empty>
                <EmptyState
                    :icon="Target"
                    :message="`The ${year} Performance Evaluations you write or may read appear here. None match your search and state.`"
                >
                    <template v-if="canCreate" #action>
                        <Button variant="secondary" as-child>
                            <Link :href="create()">New Evaluation</Link>
                        </Button>
                    </template>
                </EmptyState>
            </template>
        </DataTable>

        <Pagination :page="employees" preserve-state />
    </PageContainer>
</template>

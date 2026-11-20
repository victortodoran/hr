<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Plus, Target } from '@lucide/vue';
import DataTable from '@/components/DataTable.vue';
import EmptyState from '@/components/EmptyState.vue';
import FilterBar from '@/components/FilterBar.vue';
import FilterSearch from '@/components/FilterSearch.vue';
import FilterSelect from '@/components/FilterSelect.vue';
import PageHeader from '@/components/PageHeader.vue';
import PersonCell from '@/components/PersonCell.vue';
import ScopeSwitch from '@/components/ScopeSwitch.vue';
import PageContainer from '@/components/PageContainer.vue';
import Pagination from '@/components/Pagination.vue';
import NewPerformanceEvaluationDialog from '@/components/performance-evaluations/NewPerformanceEvaluationDialog.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { useListFilters } from '@/composables/useListFilters';
import { useOverlayRoute } from '@/composables/useOverlayRoute';
import { outcomes } from '@/lib/outcomes';
import { create, index, show } from '@/routes/performance-evaluations';
import type {
    Choice,
    CreatableYear,
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

const props = defineProps<{
    year: number;
    years: number[];
    search: string;
    state: PerformanceEvaluationState | null;
    employee: Choice | null;
    canCreate: boolean;
    employees: Paginated<PerformanceEvaluationRow>;
    creating?: boolean;
    creatableYears?: CreatableYear[];
}>();

const listQuery = computed(() => ({
    year: String(props.year),
    ...(props.search ? { search: props.search } : {}),
    ...(props.state ? { state: props.state } : {}),
    ...(props.employee ? { employee: String(props.employee.id) } : {}),
}));

const { open: dialogOpen, setOpen: setDialogOpen } = useOverlayRoute(
    () => props.creating === true,
    () => index.url({ query: listQuery.value }),
);

const columns: TableColumn[] = [
    { key: 'name', label: 'Employee' },
    { key: 'goals', label: 'Goals', align: 'end', minor: true },
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

    <PageContainer width="full">
        <PageHeader
            title="Performance Evaluations"
            description="The Performance Evaluations you write for the Employees you're the Manager of, and those you may read"
        >
            <template #actions>
                <Button v-if="canCreate" as-child>
                    <Link
                        :href="create({ query: listQuery })"
                        preserve-scroll
                        preserve-state
                    >
                        <Plus aria-hidden="true" />
                        New Performance Evaluation…
                    </Link>
                </Button>
            </template>
        </PageHeader>
        <ScopeSwitch area="performance" />

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
                <PersonCell :name="row.name" :href="show(row.evaluation.id)" />
            </template>
            <template #cell-goals="{ row }">
                <span class="tabular-nums">{{
                    row.evaluation.goals_count
                }}</span>
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
                            <Link
                                :href="create({ query: listQuery })"
                                preserve-scroll
                                preserve-state
                                >New Performance Evaluation…</Link
                            >
                        </Button>
                    </template>
                </EmptyState>
            </template>
        </DataTable>

        <Pagination :page="employees" preserve-state />

        <NewPerformanceEvaluationDialog
            v-if="canCreate"
            :open="dialogOpen"
            :creatable-years="creatableYears ?? []"
            @update:open="setDialogOpen"
        />
    </PageContainer>
</template>

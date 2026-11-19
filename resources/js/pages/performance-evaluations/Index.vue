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
    FilterChoice,
    Paginated,
    PerformanceEvaluationRow,
    TableColumn,
} from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Performance Evaluations', href: index() }],
    },
});

/**
 * For one year, the Employees the viewer effectively manages or may read
 * the Performance Evaluations of who have one for it or are still due
 * one, each with that Performance Evaluation or None yet, and a way to
 * create it when the viewer may.
 */
const props = defineProps<{
    year: number;
    years: number[];
    search: string;
    state: 'open' | 'completed' | 'none-yet' | null;
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
    { value: 'none-yet', label: 'None yet' },
];

const filters = useListFilters(index.url, {
    year: String(props.year),
    search: props.search,
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
                v-model="filters.state"
                label="State"
                any="Any state"
                :choices="states"
            />
        </FilterBar>

        <DataTable :columns="columns" :rows="employees.data">
            <template #cell-name="{ row }">
                <Link
                    v-if="row.evaluation"
                    :href="show(row.evaluation.id)"
                    class="font-medium underline-offset-4 hover:underline"
                    >{{ row.name }}</Link
                >
                <span v-else class="font-medium">{{ row.name }}</span>
            </template>
            <template #cell-goals="{ row }">
                <template v-if="row.evaluation">{{
                    row.evaluation.goals_count
                }}</template>
                <span v-else class="text-muted-foreground">—</span>
            </template>
            <template #cell-state="{ row }">
                <div
                    v-if="!row.evaluation"
                    class="flex items-center justify-end gap-2 whitespace-nowrap"
                >
                    <StatusBadge state="none-yet" />
                    <Button v-if="row.can_create" size="sm" as-child>
                        <Link
                            :aria-label="`Create ${row.name}'s ${year} Evaluation`"
                            :href="
                                create({
                                    query: { year, employee_id: row.id },
                                })
                            "
                            >Create</Link
                        >
                    </Button>
                </div>
                <div
                    v-else
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
                        <Button variant="outline" as-child>
                            <Link :href="create()">New Evaluation</Link>
                        </Button>
                    </template>
                </EmptyState>
            </template>
        </DataTable>

        <Pagination :page="employees" preserve-state />
    </PageContainer>
</template>

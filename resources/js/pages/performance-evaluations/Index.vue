<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Plus, Target } from '@lucide/vue';
import DataTable from '@/components/DataTable.vue';
import EmptyState from '@/components/EmptyState.vue';
import Heading from '@/components/Heading.vue';
import PageContainer from '@/components/PageContainer.vue';
import Pagination from '@/components/Pagination.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { outcomes } from '@/lib/outcomes';
import { create, index, show } from '@/routes/performance-evaluations';
import type { Paginated, PerformanceEvaluationRow, TableColumn } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Performance Evaluations', href: index() }],
    },
});

/**
 * For one year, the Employees the viewer effectively manages or may read
 * the Performance Evaluations of who have one for it, each with that
 * Performance Evaluation, and a way to create one when the viewer may.
 */
const props = defineProps<{
    year: number;
    years: number[];
    canCreate: boolean;
    employees: Paginated<PerformanceEvaluationRow>;
}>();

const columns: TableColumn[] = [
    { key: 'name', label: 'Employee' },
    { key: 'goals', label: 'Goals', minor: true },
    { key: 'state', label: 'Status', align: 'end' },
];

function showYear(year: unknown): void {
    router.get(
        index.url({ query: { year: String(year) } }),
        {},
        { preserveState: true, replace: true },
    );
}
</script>

<template>
    <Head title="Performance Evaluations" />

    <PageContainer width="wide">
        <Heading
            title="Performance Evaluations"
            description="The Evaluations you write for the Employees you are the Manager of, and those you may read"
        />

        <div class="flex flex-wrap gap-2">
            <Select
                :model-value="String(props.year)"
                @update:model-value="showYear"
            >
                <SelectTrigger class="w-32" aria-label="Year">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem
                        v-for="option in years"
                        :key="option"
                        :value="String(option)"
                        >{{ option }}</SelectItem
                    >
                </SelectContent>
            </Select>
            <Button v-if="canCreate" as-child>
                <Link :href="create()">
                    <Plus class="size-4" aria-hidden="true" />
                    New Evaluation
                </Link>
            </Button>
        </div>

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
                <div class="flex items-center justify-end gap-2">
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
                    :message="`The ${year} Performance Evaluations you write or may read appear here.`"
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

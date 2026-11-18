<script setup lang="ts">
import { Form, Head, Link, router } from '@inertiajs/vue3';
import { Target } from '@lucide/vue';
import PerformanceEvaluationController from '@/actions/App/Http/Controllers/Performance/PerformanceEvaluationController';
import DataTable from '@/components/DataTable.vue';
import EmptyState from '@/components/EmptyState.vue';
import Heading from '@/components/Heading.vue';
import PageContainer from '@/components/PageContainer.vue';
import Pagination from '@/components/Pagination.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { outcomes } from '@/lib/outcomes';
import { index, show } from '@/routes/performance-evaluations';
import type { Paginated, PerformanceEvaluationRow, TableColumn } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Performance Evaluations', href: index() }],
    },
});

/**
 * For one year, the Employees the viewer effectively manages or may read
 * the Performance Evaluations of, each with that year's Performance
 * Evaluation, or a way to start it for those they manage.
 */
const props = defineProps<{
    year: number;
    years: number[];
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
        </div>

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
                {{ row.evaluation ? row.evaluation.goals_count : '—' }}
            </template>
            <template #cell-state="{ row }">
                <div class="flex items-center justify-end gap-2">
                    <template v-if="row.evaluation">
                        <span
                            v-if="row.evaluation.outcome"
                            class="text-muted-foreground"
                            >{{ outcomes[row.evaluation.outcome] }}</span
                        >
                        <StatusBadge :state="row.evaluation.state" />
                        <StatusBadge
                            v-if="row.evaluation.at_risk_count > 0"
                            state="at-risk"
                            >{{ row.evaluation.at_risk_count }} At
                            Risk</StatusBadge
                        >
                    </template>
                    <Form
                        v-else-if="row.can_start"
                        v-bind="
                            PerformanceEvaluationController.store.form(row.id)
                        "
                        v-slot="{ processing }"
                    >
                        <SubmitButton
                            :processing="processing"
                            variant="outline"
                            size="sm"
                            :aria-label="`Start ${row.name}'s ${year} Performance Evaluation`"
                            >Start {{ year }}</SubmitButton
                        >
                    </Form>
                    <span v-else class="text-muted-foreground">—</span>
                </div>
            </template>
            <template #empty>
                <EmptyState
                    :icon="Target"
                    :message="`The Employees whose ${year} Evaluations you write or may read appear here.`"
                />
            </template>
        </DataTable>

        <Pagination :page="employees" preserve-state />
    </PageContainer>
</template>

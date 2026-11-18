<script setup lang="ts">
import { Head, Link, setLayoutProps } from '@inertiajs/vue3';
import { Target } from '@lucide/vue';
import GoalController from '@/actions/App/Http/Controllers/Performance/GoalController';
import ConfirmDeleteDialog from '@/components/ConfirmDeleteDialog.vue';
import DataTable from '@/components/DataTable.vue';
import EmptyState from '@/components/EmptyState.vue';
import PageContainer from '@/components/PageContainer.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { index as mine } from '@/routes/my-performance-evaluations';
import { index, show } from '@/routes/performance-evaluations';
import { create, edit } from '@/routes/performance-evaluations/goals';
import type {
    BreadcrumbItem,
    PerformanceEvaluationRecord,
    TableColumn,
} from '@/types';

/**
 * One Performance Evaluation with its Goals. Only the Employee's effective
 * Manager is offered to give, change or remove Goals, while it is Open; the
 * Employee reads them.
 */
const props = defineProps<{
    evaluation: PerformanceEvaluationRecord;
    /** Whether the Performance Evaluation is the viewer's own. */
    isOwn: boolean;
    /** Whether the viewer may give, change or remove its Goals. */
    canManageGoals: boolean;
}>();

/** My Performance Evaluations › year for the Employee; Performance Evaluations › name · year for their Manager. */
const leadingBreadcrumbs: BreadcrumbItem[] = props.isOwn
    ? [{ title: 'My Performance Evaluations', href: mine() }]
    : [
          {
              title: 'Performance Evaluations',
              href: index({ query: { year: String(props.evaluation.year) } }),
          },
      ];

const columns: TableColumn[] = [
    { key: 'goal', label: 'Goal' },
    ...(props.canManageGoals
        ? [{ key: 'actions', label: 'Actions', align: 'end' as const }]
        : []),
];

const title = props.isOwn
    ? String(props.evaluation.year)
    : `${props.evaluation.employee.name} · ${props.evaluation.year}`;

setLayoutProps({
    breadcrumbs: [
        ...leadingBreadcrumbs,
        { title, href: show(props.evaluation.id) },
    ],
});
</script>

<template>
    <Head :title="`Performance Evaluation · ${title}`" />

    <PageContainer width="wide">
        <header class="flex flex-wrap items-start justify-between gap-4">
            <div class="flex min-w-0 flex-col gap-1">
                <h1 class="text-xl font-semibold tracking-tight">
                    {{ evaluation.employee.name }}
                    <span class="font-normal text-muted-foreground"
                        >· {{ evaluation.year }} Performance Evaluation</span
                    >
                </h1>
                <div class="flex items-center gap-2 text-sm">
                    <StatusBadge :state="evaluation.state" />
                </div>
            </div>
            <Button v-if="canManageGoals" as-child>
                <Link :href="create(evaluation.id)">Give a Goal</Link>
            </Button>
        </header>

        <section class="space-y-2">
            <h2 class="text-sm font-medium">Goals</h2>
            <DataTable :columns="columns" :rows="evaluation.goals">
                <template #cell-goal="{ row }">
                    <p class="font-medium">{{ row.title }}</p>
                    <p class="whitespace-pre-line text-muted-foreground">
                        {{ row.description }}
                    </p>
                </template>
                <template #cell-actions="{ row }">
                    <div class="flex justify-end gap-2">
                        <Button variant="outline" size="sm" as-child>
                            <Link
                                :href="
                                    edit({
                                        performanceEvaluation: evaluation.id,
                                        goal: row.id,
                                    })
                                "
                                :aria-label="`Change ${row.title}`"
                                >Change</Link
                            >
                        </Button>
                        <ConfirmDeleteDialog
                            :form="
                                GoalController.destroy.form({
                                    performanceEvaluation: evaluation.id,
                                    goal: row.id,
                                })
                            "
                            :title="`Remove “${row.title}”?`"
                            description="The Goal is removed from this Performance Evaluation for good."
                            confirm-label="Remove Goal"
                            :error-bag="`goal-${row.id}`"
                        >
                            <template #trigger>
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    class="text-destructive"
                                    :aria-label="`Remove ${row.title}`"
                                    >Remove</Button
                                >
                            </template>
                        </ConfirmDeleteDialog>
                    </div>
                </template>
                <template #empty>
                    <EmptyState
                        :icon="Target"
                        :message="
                            isOwn
                                ? 'The Goals your Manager gives you for this year appear here.'
                                : 'The Goals you give for this year appear here.'
                        "
                    >
                        <template v-if="canManageGoals" #action>
                            <Button as-child variant="outline">
                                <Link :href="create(evaluation.id)"
                                    >Give a Goal</Link
                                >
                            </Button>
                        </template>
                    </EmptyState>
                </template>
            </DataTable>
        </section>
    </PageContainer>
</template>

<script setup lang="ts">
import { Form, Head, setLayoutProps } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { MessageSquare } from '@lucide/vue';
import GoalCommentController from '@/actions/App/Http/Controllers/Performance/GoalCommentController';
import GoalProgressController from '@/actions/App/Http/Controllers/Performance/GoalProgressController';
import DataTable from '@/components/DataTable.vue';
import EmptyState from '@/components/EmptyState.vue';
import InputError from '@/components/InputError.vue';
import PageContainer from '@/components/PageContainer.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { formatDate, formatDateTime } from '@/lib/dates';
import { progresses } from '@/lib/progress';
import { index as mine } from '@/routes/my-performance-evaluations';
import { index, show } from '@/routes/performance-evaluations';
import { show as showGoal } from '@/routes/performance-evaluations/goals';
import type {
    BreadcrumbItem,
    GoalComment,
    GoalRecord,
    PerformanceEvaluationSummary,
    Progress,
    ProgressChange,
    TableColumn,
} from '@/types';

/**
 * One Goal on its own page: its title, description and Progress, and
 * every change to the Progress, newest first, back to Not Started when it
 * was given, and the thread of Comments on it, oldest first. The
 * Employee and their effective Manager change the Progress and write
 * Comments while the Performance Evaluation is Open; anyone else who can
 * see it reads them.
 */
const props = defineProps<{
    evaluation: PerformanceEvaluationSummary;
    goal: GoalRecord;
    history: ProgressChange[];
    comments: GoalComment[];
    /** Whether the Performance Evaluation is the viewer's own. */
    isOwn: boolean;
    /** Whether the viewer may change the Goal's Progress. */
    canChangeProgress: boolean;
    /** Whether the viewer may write Comments on the Goal. */
    canComment: boolean;
}>();

/** The Progress chosen in the form, the Goal's own until changed. */
const chosenProgress = ref<Progress>(props.goal.progress);

watch(
    () => props.goal.progress,
    (progress) => (chosenProgress.value = progress),
);

/** One line of the history: a change, or the Goal starting Not Started. */
type HistoryRow = {
    key: string;
    progress: Progress;
    description: string;
    when: string;
};

const historyRows = computed<HistoryRow[]>(() => [
    ...props.history.map((change, position) => ({
        key: `change-${position}`,
        progress: change.progress,
        description: `${change.changed_by} changed it from ${progresses[change.previous_progress]}`,
        when: formatDateTime(change.changed_at),
    })),
    {
        key: 'given',
        progress: 'not-started',
        description: 'The Goal was given',
        when: formatDate(props.goal.given_at),
    },
]);

const historyColumns: TableColumn[] = [
    { key: 'progress', label: 'Progress' },
    { key: 'description', label: 'Change' },
    { key: 'when', label: 'When', minor: true },
];

const evaluationTitle = props.isOwn
    ? String(props.evaluation.year)
    : `${props.evaluation.employee.name} · ${props.evaluation.year}`;

/** Performance › year for the Employee; Performance Evaluations › name · year for anyone else. */
const leadingBreadcrumbs: BreadcrumbItem[] = props.isOwn
    ? [{ title: 'Performance', href: mine() }]
    : [
          {
              title: 'Performance Evaluations',
              href: index({ query: { year: String(props.evaluation.year) } }),
          },
      ];

setLayoutProps({
    breadcrumbs: [
        ...leadingBreadcrumbs,
        { title: evaluationTitle, href: show(props.evaluation.id) },
        {
            title: props.goal.title,
            href: showGoal({
                performanceEvaluation: props.evaluation.id,
                goal: props.goal.id,
            }),
        },
    ],
});
</script>

<template>
    <Head :title="`Goal · ${goal.title}`" />

    <PageContainer width="wide">
        <header class="flex flex-col gap-1">
            <h1 class="text-xl font-semibold tracking-tight">
                {{ goal.title }}
            </h1>
            <p class="text-sm text-muted-foreground">
                {{ evaluation.employee.name }} · {{ evaluation.year }}
                Performance Evaluation
            </p>
            <p class="whitespace-pre-line">{{ goal.description }}</p>
        </header>

        <section class="space-y-2">
            <h2 class="text-sm font-medium">Progress</h2>
            <Form
                v-if="canChangeProgress"
                v-bind="
                    GoalProgressController.update.form({
                        performanceEvaluation: evaluation.id,
                        goal: goal.id,
                    })
                "
                :options="{ preserveScroll: true }"
                class="flex flex-wrap items-end gap-2"
                v-slot="{ errors, processing }"
            >
                <div class="grid gap-2">
                    <Label for="goal-progress" class="sr-only">Progress</Label>
                    <Select
                        name="progress"
                        required
                        :model-value="chosenProgress"
                        @update:model-value="
                            chosenProgress = $event as Progress
                        "
                    >
                        <SelectTrigger id="goal-progress" class="w-40">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="(label, progress) in progresses"
                                :key="progress"
                                :value="progress"
                                >{{ label }}</SelectItem
                            >
                        </SelectContent>
                    </Select>
                </div>
                <SubmitButton
                    variant="outline"
                    :processing="processing"
                    :disabled="chosenProgress === goal.progress"
                    >Save Progress</SubmitButton
                >
                <InputError class="w-full" :message="errors.progress" />
            </Form>
            <StatusBadge v-else :state="goal.progress" />
        </section>

        <section class="space-y-2">
            <h2 class="text-sm font-medium">Comments</h2>
            <ol v-if="comments.length > 0" class="space-y-3">
                <li
                    v-for="comment in comments"
                    :key="comment.id"
                    class="space-y-0.5 text-sm"
                >
                    <p class="text-muted-foreground">
                        <span class="font-medium text-foreground">{{
                            comment.author
                        }}</span>
                        · {{ formatDateTime(comment.written_at) }}
                    </p>
                    <p class="whitespace-pre-line">{{ comment.body }}</p>
                </li>
            </ol>
            <EmptyState
                v-else
                :icon="MessageSquare"
                :message="`What ${evaluation.employee.name} and their Manager write about this Goal appears here.`"
            />
            <Form
                v-if="canComment"
                v-bind="
                    GoalCommentController.store.form({
                        performanceEvaluation: evaluation.id,
                        goal: goal.id,
                    })
                "
                reset-on-success
                :options="{ preserveScroll: true }"
                class="flex flex-wrap items-start gap-2"
                v-slot="{ errors, processing }"
            >
                <div class="grid min-w-0 flex-1 gap-2">
                    <Label for="goal-comment" class="sr-only">Comment</Label>
                    <Input
                        id="goal-comment"
                        name="body"
                        placeholder="Write a Comment"
                        maxlength="5000"
                        required
                    />
                    <InputError :message="errors.body" />
                </div>
                <SubmitButton :processing="processing">Comment</SubmitButton>
            </Form>
        </section>

        <section class="space-y-2">
            <h2 class="text-sm font-medium">History</h2>
            <DataTable
                :columns="historyColumns"
                :rows="historyRows"
                :row-key="(row) => row.key"
            >
                <template #cell-progress="{ row }">
                    <StatusBadge :state="row.progress" />
                </template>
                <template #cell-description="{ row }">
                    {{ row.description }}
                    <span class="block text-muted-foreground sm:hidden">{{
                        row.when
                    }}</span>
                </template>
            </DataTable>
        </section>
    </PageContainer>
</template>

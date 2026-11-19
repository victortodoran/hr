<script setup lang="ts">
import { Form, Head, router, setLayoutProps, useForm } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { Check, MessageSquare } from '@lucide/vue';
import { toast } from 'vue-sonner';
import GoalController from '@/actions/App/Http/Controllers/Performance/GoalController';
import GoalCommentController from '@/actions/App/Http/Controllers/Performance/GoalCommentController';
import GoalProgressController from '@/actions/App/Http/Controllers/Performance/GoalProgressController';
import ConfirmDeleteDialog from '@/components/ConfirmDeleteDialog.vue';
import DataTable from '@/components/DataTable.vue';
import EmptyState from '@/components/EmptyState.vue';
import InputError from '@/components/InputError.vue';
import PageContainer from '@/components/PageContainer.vue';
import GoalFields from '@/components/performance-evaluations/GoalFields.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { formatDate, formatDateTime } from '@/lib/dates';
import { goalRemovalWarning } from '@/lib/goals';
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
 * see it reads them. Picking a Progress saves it straight away. Their
 * effective Manager also changes the Goal's title and description here,
 * opened straight into the fields with `?editing=1`, or removes it.
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
    /** Whether the viewer may change or remove the Goal itself. */
    canEdit: boolean;
    /** Whether it opens with the title and description as fields (`?editing=1`). */
    startsEditing: boolean;
}>();

const goalRoute = {
    performanceEvaluation: props.evaluation.id,
    goal: props.goal.id,
};

/** The Progress showing in the dropdown, the Goal's own until another is picked. */
const chosenProgress = ref<Progress>(props.goal.progress);

watch(
    () => props.goal.progress,
    (progress) => (chosenProgress.value = progress),
);

/** Whether a picked Progress is being saved. */
const savingProgress = ref(false);

/** Whether the brief "Saved" tick shows beside the dropdown. */
const progressSaved = ref(false);

let hideSavedTick: ReturnType<typeof setTimeout> | undefined;

onBeforeUnmount(() => clearTimeout(hideSavedTick));

/**
 * Save the picked Progress straight away. When saving fails, for
 * example because the Performance Evaluation was Completed meanwhile,
 * the dropdown goes back to the Goal's Progress and a toast says why.
 */
function saveProgress(progress: Progress): void {
    if (progress === props.goal.progress) {
        return;
    }

    chosenProgress.value = progress;

    const refuse = (message: string): void => {
        chosenProgress.value = props.goal.progress;
        toast.error(message);
    };

    router.put(
        GoalProgressController.update.url(goalRoute),
        { progress },
        {
            preserveScroll: true,
            onStart: () => {
                savingProgress.value = true;
                progressSaved.value = false;
            },
            onFinish: () => (savingProgress.value = false),
            onSuccess: () => {
                progressSaved.value = true;
                clearTimeout(hideSavedTick);
                hideSavedTick = setTimeout(
                    () => (progressSaved.value = false),
                    2000,
                );
            },
            onError: (errors) =>
                refuse(errors.progress ?? 'The Progress wasn’t saved.'),
            onHttpException: (response) => {
                if (response.status !== 403) {
                    refuse('The Progress wasn’t saved. Try again.');

                    return false;
                }

                refuse(
                    'The Progress wasn’t saved: you can’t change it any more, as the Performance Evaluation may have been Completed.',
                );
                router.reload();

                return false;
            },
            onNetworkError: () => {
                refuse(
                    'The Progress wasn’t saved: the connection failed. Try again.',
                );

                return false;
            },
        },
    );
}

/** Whether the Goal's title and description show as fields. */
const editing = ref(props.startsEditing);

const goalForm = useForm({
    title: props.goal.title,
    description: props.goal.description,
});

/** Open the fields on the Goal's title and description as they are now. */
function startEditing(): void {
    goalForm.defaults({
        title: props.goal.title,
        description: props.goal.description,
    });
    goalForm.reset();
    goalForm.clearErrors();
    editing.value = true;
}

/** Put the text back and close the fields. */
function cancelEditing(): void {
    goalForm.reset();
    goalForm.clearErrors();
    editing.value = false;
}

/** Save the change and stay here; errors keep the fields open. */
function saveGoal(): void {
    goalForm.submit(GoalController.update(goalRoute), {
        preserveScroll: true,
        onSuccess: () => (editing.value = false),
    });
}

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
        { title: props.goal.title, href: showGoal(goalRoute) },
    ],
});
</script>

<template>
    <Head :title="`Goal · ${goal.title}`" />

    <PageContainer width="wide">
        <form
            v-if="editing"
            class="flex flex-col gap-4"
            @submit.prevent="saveGoal"
        >
            <div class="flex flex-col gap-1">
                <h1 class="sr-only">{{ goal.title }}</h1>
                <p class="text-sm text-muted-foreground">
                    {{ evaluation.employee.name }} · {{ evaluation.year }}
                    Performance Evaluation
                </p>
            </div>
            <GoalFields
                v-model:title="goalForm.title"
                v-model:description="goalForm.description"
                :errors="goalForm.errors"
            />
            <div class="flex gap-2">
                <SubmitButton :processing="goalForm.processing"
                    >Save Goal</SubmitButton
                >
                <Button type="button" variant="ghost" @click="cancelEditing"
                    >Cancel</Button
                >
            </div>
        </form>
        <header v-else class="flex flex-wrap items-start justify-between gap-4">
            <div class="flex min-w-0 flex-col gap-1">
                <h1 class="text-xl font-semibold tracking-tight">
                    {{ goal.title }}
                </h1>
                <p class="text-sm text-muted-foreground">
                    {{ evaluation.employee.name }} · {{ evaluation.year }}
                    Performance Evaluation
                </p>
                <p class="whitespace-pre-line">{{ goal.description }}</p>
            </div>
            <div v-if="canEdit" class="flex gap-2">
                <Button variant="outline" @click="startEditing">Edit</Button>
                <ConfirmDeleteDialog
                    :form="GoalController.destroy.form(goalRoute)"
                    :title="`Remove “${goal.title}”?`"
                    :description="goalRemovalWarning(comments.length)"
                    confirm-label="Remove Goal"
                    :error-bag="`goal-${goal.id}`"
                >
                    <template #trigger>
                        <Button variant="ghost" class="text-destructive"
                            >Remove</Button
                        >
                    </template>
                </ConfirmDeleteDialog>
            </div>
        </header>

        <section class="space-y-2">
            <h2 class="text-sm font-medium">Progress</h2>
            <div
                v-if="canChangeProgress"
                class="flex flex-wrap items-center gap-2"
            >
                <Label for="goal-progress" class="sr-only">Progress</Label>
                <Select
                    :model-value="chosenProgress"
                    :disabled="savingProgress"
                    @update:model-value="saveProgress($event as Progress)"
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
                <span
                    class="inline-flex items-center gap-1 text-sm text-muted-foreground"
                    aria-live="polite"
                >
                    <template v-if="progressSaved">
                        <Check class="size-4" aria-hidden="true" />
                        Saved
                    </template>
                </span>
            </div>
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
                v-bind="GoalCommentController.store.form(goalRoute)"
                reset-on-success
                :options="{ preserveScroll: true }"
                class="flex flex-wrap items-start gap-2"
                v-slot="{ errors, processing }"
            >
                <div class="grid min-w-0 flex-1 gap-2">
                    <Label for="goal-comment" class="sr-only">Comment</Label>
                    <Textarea
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

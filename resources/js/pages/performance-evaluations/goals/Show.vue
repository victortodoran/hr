<script setup lang="ts">
import { Form, Head, setLayoutProps, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { MessageSquare } from '@lucide/vue';
import GoalController from '@/actions/App/Http/Controllers/Performance/GoalController';
import GoalCommentController from '@/actions/App/Http/Controllers/Performance/GoalCommentController';
import GoalProgressController from '@/actions/App/Http/Controllers/Performance/GoalProgressController';
import ConfirmDeleteDialog from '@/components/ConfirmDeleteDialog.vue';
import EmptyState from '@/components/EmptyState.vue';
import InputError from '@/components/InputError.vue';
import Heading from '@/components/Heading.vue';
import PageContainer from '@/components/PageContainer.vue';
import SavedTick from '@/components/SavedTick.vue';
import GoalFields from '@/components/performance-evaluations/GoalFields.vue';
import GoalOutcomeSelect from '@/components/performance-evaluations/GoalOutcomeSelect.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { getInitials } from '@/composables/useInitials';
import { useSaveOnPick } from '@/composables/useSaveOnPick';
import { formatDate, formatDateTime } from '@/lib/dates';
import { goalRemovalWarning } from '@/lib/goals';
import { outcomes } from '@/lib/outcomes';
import { progresses } from '@/lib/progress';
import {
    progressDotColours,
    statusColours,
    statusPillTrigger,
} from '@/lib/statusMeta';
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
} from '@/types';

/**
 * One Goal on its own page: its title and description with the thread of
 * Comments on it, oldest first, and beside them its Progress, its Outcome
 * and every change to the Progress, newest first, back to Not Started when
 * it was given. The Employee and their effective Manager change the
 * Progress and write Comments while the Performance Evaluation is Open;
 * anyone else who can see it reads them. Their effective Manager also
 * gives, changes or clears its Outcome, and changes the Goal's title and
 * description here, opened straight into the fields with `?editing=1`, or
 * removes it; anyone else who may see Outcomes reads the one given.
 * Picking a Progress or an Outcome saves it straight away.
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
    /** Whether the viewer may change or remove the Goal itself and give its Outcome, which means they also see it. */
    canEdit: boolean;
    /** Whether the viewer sees its Outcome. */
    canSeeOutcomes: boolean;
    /** Whether it opens with the title and description as fields (`?editing=1`). */
    startsEditing: boolean;
}>();

const goalRoute = {
    performanceEvaluation: props.evaluation.id,
    goal: props.goal.id,
};

/** The Progress dropdown, which saves what is picked straight away. */
const progress = useSaveOnPick<Progress>({
    saved: () => props.goal.progress,
    url: () => GoalProgressController.update.url(goalRoute),
    field: 'progress',
    label: 'Progress',
});

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
        description: `${change.changed_by} · from ${progresses[change.previous_progress]}`,
        when: formatDateTime(change.changed_at),
    })),
    {
        key: 'given',
        progress: 'not-started',
        description: 'The Goal was given',
        when: formatDate(props.goal.given_at),
    },
]);

/** How many History lines show until the viewer asks for all of them. */
const historyPreviewLength = 5;

/** Whether every History line shows, not just the latest few. */
const showingAllHistory = ref(false);

const shownHistoryRows = computed(() =>
    showingAllHistory.value
        ? historyRows.value
        : historyRows.value.slice(0, historyPreviewLength),
);

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
        <Heading
            :title="goal.title"
            :description="`${evaluation.employee.name} · ${evaluation.year} Performance Evaluation`"
        >
            <template v-if="canEdit && !editing" #actions>
                <Button variant="secondary" @click="startEditing"
                    >Change</Button
                >
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
            </template>
        </Heading>

        <div class="flex flex-wrap items-start gap-6">
            <div class="flex min-w-0 flex-[999_1_28rem] flex-col gap-8">
                <Card v-if="editing" class="px-6 py-5">
                    <form
                        class="flex flex-col gap-4"
                        @submit.prevent="saveGoal"
                    >
                        <GoalFields
                            v-model:title="goalForm.title"
                            v-model:description="goalForm.description"
                            :errors="goalForm.errors"
                        />
                        <div class="flex gap-2">
                            <SubmitButton :processing="goalForm.processing"
                                >Save Goal</SubmitButton
                            >
                            <Button
                                type="button"
                                variant="ghost"
                                @click="cancelEditing"
                                >Cancel</Button
                            >
                        </div>
                    </form>
                </Card>
                <Card v-else class="gap-2 px-6 py-5">
                    <h2
                        class="text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                    >
                        What achieving it looks like
                    </h2>
                    <p class="text-[0.95rem]/relaxed whitespace-pre-line">
                        {{ goal.description }}
                    </p>
                </Card>

                <section class="flex flex-col gap-4" aria-labelledby="comments">
                    <h2
                        id="comments"
                        class="text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                    >
                        Comments · {{ comments.length }}
                    </h2>
                    <ol v-if="comments.length > 0" class="flex flex-col gap-4">
                        <li
                            v-for="comment in comments"
                            :key="comment.id"
                            class="flex gap-3"
                        >
                            <Avatar class="size-8 shrink-0">
                                <AvatarFallback class="text-xs font-semibold">{{
                                    getInitials(comment.author)
                                }}</AvatarFallback>
                            </Avatar>
                            <div class="flex min-w-0 flex-col gap-0.5 text-sm">
                                <p>
                                    <span class="font-semibold">{{
                                        comment.author
                                    }}</span>
                                    <span class="text-muted-foreground">
                                        ·
                                        {{
                                            formatDateTime(comment.written_at)
                                        }}</span
                                    >
                                </p>
                                <p class="whitespace-pre-line">
                                    {{ comment.body }}
                                </p>
                            </div>
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
                        v-slot="{ errors, processing }"
                    >
                        <Card class="gap-0 overflow-hidden p-0">
                            <Label for="goal-comment" class="sr-only"
                                >Comment</Label
                            >
                            <Textarea
                                id="goal-comment"
                                name="body"
                                placeholder="Write a Comment"
                                maxlength="5000"
                                required
                                class="min-h-20 resize-none rounded-none border-0 shadow-none focus-visible:ring-0"
                            />
                            <div class="flex justify-end px-2 pb-2">
                                <SubmitButton size="sm" :processing="processing"
                                    >Comment</SubmitButton
                                >
                            </div>
                        </Card>
                        <InputError class="mt-2" :message="errors.body" />
                    </Form>
                </section>
            </div>

            <aside
                class="w-full flex-[1_1_16rem] sm:max-w-xs"
                aria-label="Progress, Outcome and History"
            >
                <Card class="gap-5 p-5">
                    <section class="flex flex-col gap-2">
                        <h2
                            class="text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                        >
                            Progress
                        </h2>
                        <div
                            v-if="canChangeProgress"
                            class="flex items-center gap-2"
                        >
                            <Select
                                :model-value="progress.chosen.value"
                                :disabled="progress.saving.value"
                                @update:model-value="
                                    progress.save($event as Progress)
                                "
                            >
                                <SelectTrigger
                                    id="goal-progress"
                                    size="sm"
                                    aria-label="Progress"
                                    :class="[
                                        statusPillTrigger,
                                        statusColours[progress.chosen.value],
                                    ]"
                                >
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="(label, value) in progresses"
                                        :key="value"
                                        :value="value"
                                        >{{ label }}</SelectItem
                                    >
                                </SelectContent>
                            </Select>
                            <SavedTick :shown="progress.justSaved.value" />
                        </div>
                        <StatusBadge v-else :state="goal.progress" />
                    </section>

                    <section
                        v-if="canEdit || (canSeeOutcomes && goal.outcome)"
                        class="flex flex-col gap-2"
                    >
                        <h2
                            class="text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                        >
                            Outcome
                        </h2>
                        <GoalOutcomeSelect
                            v-if="canEdit"
                            :outcome="goal.outcome"
                            :performance-evaluation-id="evaluation.id"
                            :goal-id="goal.id"
                            label="Outcome"
                        />
                        <span
                            v-else-if="goal.outcome"
                            class="inline-flex w-fit rounded-full border px-2.5 py-0.5 text-xs font-medium"
                            >{{ outcomes[goal.outcome] }}</span
                        >
                    </section>

                    <div class="h-px bg-border" aria-hidden="true" />

                    <section class="flex flex-col gap-3">
                        <h2
                            class="text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                        >
                            History
                        </h2>
                        <ol class="ml-1 flex flex-col gap-3.5 border-l pl-4">
                            <li
                                v-for="row in shownHistoryRows"
                                :key="row.key"
                                class="relative text-sm"
                            >
                                <span
                                    class="absolute top-1.5 -left-[1.3rem] size-2 rounded-full ring-4 ring-card"
                                    :class="progressDotColours[row.progress]"
                                    aria-hidden="true"
                                />
                                <p class="font-medium">
                                    {{ progresses[row.progress] }}
                                </p>
                                <p class="text-xs text-muted-foreground">
                                    {{ row.description }} · {{ row.when }}
                                </p>
                            </li>
                        </ol>
                        <Button
                            v-if="historyRows.length > historyPreviewLength"
                            variant="link"
                            size="sm"
                            class="h-auto self-start p-0"
                            @click="showingAllHistory = !showingAllHistory"
                        >
                            {{
                                showingAllHistory
                                    ? 'Show fewer'
                                    : `Show all ${historyRows.length} changes`
                            }}
                        </Button>
                    </section>
                </Card>
            </aside>
        </div>
    </PageContainer>
</template>

<script setup lang="ts">
import { Form, Head, setLayoutProps, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import GoalController from '@/actions/App/Http/Controllers/Performance/GoalController';
import GoalOutcomeController from '@/actions/App/Http/Controllers/Performance/GoalOutcomeController';
import GoalCommentController from '@/actions/App/Http/Controllers/Performance/GoalCommentController';
import GoalProgressController from '@/actions/App/Http/Controllers/Performance/GoalProgressController';
import ConfirmDeleteDialog from '@/components/ConfirmDeleteDialog.vue';
import InputError from '@/components/InputError.vue';
import PageContainer from '@/components/PageContainer.vue';
import SavedTick from '@/components/SavedTick.vue';
import GoalFields from '@/components/performance-evaluations/GoalFields.vue';
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
import { statusColours, statusPillTrigger } from '@/lib/status';
import { index as mine } from '@/routes/my-performance-evaluations';
import { index, show } from '@/routes/performance-evaluations';
import { show as showGoal } from '@/routes/performance-evaluations/goals';
import type {
    BreadcrumbItem,
    GoalComment,
    GoalRecord,
    Outcome,
    PerformanceEvaluationSummary,
    Progress,
    ProgressChange,
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
    /** Whether the viewer may change or remove the Goal itself, and give its Outcome. */
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
const {
    chosen: chosenProgress,
    saving: savingProgress,
    justSaved: progressSaved,
    save: saveProgress,
} = useSaveOnPick<Progress>({
    saved: () => props.goal.progress,
    url: () => GoalProgressController.update.url(goalRoute),
    field: 'progress',
    label: 'Progress',
});

/** The value the Outcome dropdown shows while no Outcome is given. */
const notGiven = 'not-given';

/** The Outcome dropdown, which saves what is picked straight away. */
const outcome = useSaveOnPick<Outcome | null>({
    saved: () => props.goal.outcome,
    url: () => GoalOutcomeController.update.url(goalRoute),
    field: 'outcome',
    label: 'Outcome',
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

/** Each Progress's dot on the History timeline, in its status colour. */
const progressDots: Record<Progress, string> = {
    'not-started': 'bg-status-pending-foreground',
    'on-track': 'bg-status-upcoming-foreground',
    'at-risk': 'bg-status-declined-foreground',
    done: 'bg-status-approved-foreground',
};

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
        <header class="flex flex-wrap items-start justify-between gap-4">
            <div class="flex min-w-0 flex-col gap-1.5">
                <h1 class="text-2xl font-semibold tracking-tight">
                    {{ goal.title }}
                </h1>
                <p class="text-sm text-muted-foreground">
                    {{ evaluation.employee.name }} · {{ evaluation.year }}
                    Performance Evaluation
                </p>
            </div>
            <div v-if="canEdit && !editing" class="flex gap-2">
                <Button variant="outline" @click="startEditing">Change</Button>
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
                    <p v-else class="text-sm text-muted-foreground">
                        What {{ evaluation.employee.name }} and their Manager
                        write about this Goal appears here.
                    </p>
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
                class="flex w-full flex-[1_1_16rem] flex-col gap-5 rounded-xl border bg-card p-5 text-card-foreground shadow-sm sm:max-w-xs"
                aria-label="Progress, Outcome and History"
            >
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
                            :model-value="chosenProgress"
                            :disabled="savingProgress"
                            @update:model-value="
                                saveProgress($event as Progress)
                            "
                        >
                            <SelectTrigger
                                id="goal-progress"
                                size="sm"
                                aria-label="Progress"
                                :class="[
                                    statusPillTrigger,
                                    statusColours[chosenProgress],
                                ]"
                            >
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
                        <SavedTick :shown="progressSaved" />
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
                    <div v-if="canEdit" class="flex items-center gap-2">
                        <Select
                            :model-value="outcome.chosen.value ?? notGiven"
                            :disabled="outcome.saving.value"
                            @update:model-value="
                                outcome.save(
                                    $event === notGiven
                                        ? null
                                        : ($event as Outcome),
                                )
                            "
                        >
                            <SelectTrigger
                                size="sm"
                                aria-label="Outcome"
                                :class="[
                                    statusPillTrigger,
                                    outcome.chosen.value
                                        ? 'bg-primary text-primary-foreground'
                                        : 'bg-status-closed text-status-closed-foreground',
                                ]"
                            >
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem :value="notGiven"
                                    >Not given</SelectItem
                                >
                                <SelectItem
                                    v-for="(label, value) in outcomes"
                                    :key="value"
                                    :value="value"
                                    >{{ label }}</SelectItem
                                >
                            </SelectContent>
                        </Select>
                        <SavedTick :shown="outcome.justSaved.value" />
                    </div>
                    <span
                        v-else-if="goal.outcome"
                        class="inline-flex w-fit rounded-full bg-primary px-2.5 py-0.5 text-xs font-medium text-primary-foreground"
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
                                :class="progressDots[row.progress]"
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
                    <button
                        v-if="historyRows.length > historyPreviewLength"
                        type="button"
                        class="self-start text-sm font-medium underline-offset-4 hover:underline"
                        @click="showingAllHistory = !showingAllHistory"
                    >
                        {{
                            showingAllHistory
                                ? 'Show fewer'
                                : `Show all ${historyRows.length} changes`
                        }}
                    </button>
                </section>
            </aside>
        </div>
    </PageContainer>
</template>

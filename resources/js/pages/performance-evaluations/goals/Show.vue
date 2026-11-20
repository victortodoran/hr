<script setup lang="ts">
import { Form, Head, Link, setLayoutProps } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { MessageSquare } from '@lucide/vue';
import GoalController from '@/actions/App/Http/Controllers/Performance/GoalController';
import GoalCommentController from '@/actions/App/Http/Controllers/Performance/GoalCommentController';
import ConfirmDeleteDialog from '@/components/ConfirmDeleteDialog.vue';
import EmptyState from '@/components/EmptyState.vue';
import InputError from '@/components/InputError.vue';
import MoreMenu from '@/components/MoreMenu.vue';
import PageContainer from '@/components/PageContainer.vue';
import PageHeader from '@/components/PageHeader.vue';
import GoalOutcomeSelect from '@/components/performance-evaluations/GoalOutcomeSelect.vue';
import GoalProgressSelect from '@/components/performance-evaluations/GoalProgressSelect.vue';
import GoalSheet from '@/components/performance-evaluations/GoalSheet.vue';
import SidePanel from '@/components/SidePanel.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { DropdownMenuItem } from '@/components/ui/dropdown-menu';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { getInitials } from '@/composables/useInitials';
import { useOverlayRoute } from '@/composables/useOverlayRoute';
import { formatDate, formatDateTime } from '@/lib/dates';
import { goalDeletionWarning, goalOutcomeLabel } from '@/lib/goals';
import { outcomes } from '@/lib/outcomes';
import { progresses } from '@/lib/progress';
import { progressDotColours } from '@/lib/statusMeta';
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
 * One Goal on its own page (rule L12): its title and description with the
 * thread of Comments on it, oldest first, and beside them, from 1280px
 * (L6), its Progress, its Outcome and every change to the Progress,
 * newest first, back to Not Started when it was added (a timeline, K1),
 * each starting with its StatusBadge word. The Employee and their
 * effective Manager change the Progress and add Comments (⌘↵ sends one)
 * while the Performance Evaluation is Open; anyone else who can see it
 * reads them. Their effective Manager also gives, changes or clears its
 * Outcome, edits the Goal in the Edit Goal sheet, whose own URL is
 * `?editing=1` (L14), or deletes it from the ⋯ menu (X3); anyone else who
 * may see Outcomes reads the one given. Picking a Progress or an Outcome
 * saves it straight away.
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
    /** Whether the viewer may add Comments to the Goal. */
    canComment: boolean;
    /** Whether the viewer may edit or delete the Goal itself and give its Outcome, which means they also see it. */
    canEdit: boolean;
    /** Whether the viewer sees its Outcome. */
    canSeeOutcomes: boolean;
    /** Whether the Edit Goal sheet is open (`?editing=1`). */
    startsEditing: boolean;
}>();

const goalRoute = {
    performanceEvaluation: props.evaluation.id,
    goal: props.goal.id,
};

const { open: editOpen, setOpen: setEditOpen } = useOverlayRoute(
    () => props.startsEditing,
    () => showGoal(goalRoute).url,
);

/** Whether the confirmation before deleting the Goal is showing. */
const confirmingDeletion = ref(false);

/** ⌘↵ or Ctrl↵ in the Comment box adds the Comment. */
function sendOnModifierEnter(event: KeyboardEvent): void {
    if (event.key === 'Enter' && (event.metaKey || event.ctrlKey)) {
        event.preventDefault();
        (event.target as HTMLTextAreaElement).form?.requestSubmit();
    }
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
        description: 'The Goal was added',
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

/** Whose Performance Evaluation the Goal belongs to, under its title and in its sheet. */
const evaluationDescription = `${props.evaluation.employee.name} · ${props.evaluation.year} Performance Evaluation`;

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

    <PageContainer width="full">
        <PageHeader :title="goal.title" :description="evaluationDescription">
            <template v-if="canEdit" #actions>
                <Button variant="secondary" as-child>
                    <Link
                        :href="showGoal(goalRoute, { query: { editing: '1' } })"
                        preserve-scroll
                        preserve-state
                        >Edit</Link
                    >
                </Button>
                <MoreMenu label="More for this Goal">
                    <DropdownMenuItem
                        variant="destructive"
                        @select="confirmingDeletion = true"
                        >Delete Goal…</DropdownMenuItem
                    >
                </MoreMenu>
            </template>
        </PageHeader>

        <div class="grid items-start gap-8 xl:grid-cols-[minmax(0,1fr)_20rem]">
            <div class="flex min-w-0 flex-col gap-8">
                <section
                    class="flex flex-col gap-2"
                    aria-labelledby="goal-description"
                >
                    <h2 id="goal-description" class="text-section">
                        What achieving it looks like
                    </h2>
                    <p class="max-w-prose text-body whitespace-pre-line">
                        {{ goal.description }}
                    </p>
                </section>

                <section class="flex flex-col gap-4" aria-labelledby="comments">
                    <h2 id="comments" class="text-section">
                        Comments · {{ comments.length }}
                    </h2>
                    <ol v-if="comments.length > 0" class="flex flex-col gap-4">
                        <li
                            v-for="comment in comments"
                            :key="comment.id"
                            class="flex gap-3"
                        >
                            <Avatar class="size-8 shrink-0">
                                <AvatarFallback
                                    class="text-caption font-semibold"
                                    >{{
                                        getInitials(comment.author)
                                    }}</AvatarFallback
                                >
                            </Avatar>
                            <div
                                class="flex min-w-0 flex-col gap-0.5 text-body"
                            >
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
                                <p class="break-words whitespace-pre-line">
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
                        class="grid gap-2"
                        v-slot="{ errors, processing }"
                    >
                        <Label for="goal-comment">Comment</Label>
                        <Textarea
                            id="goal-comment"
                            name="body"
                            placeholder="e.g. The API review is booked for Friday."
                            maxlength="5000"
                            required
                            class="min-h-20"
                            aria-describedby="goal-comment-help"
                            @keydown="sendOnModifierEnter"
                        />
                        <InputError :message="errors.body" />
                        <div class="flex items-center justify-between gap-3">
                            <p
                                id="goal-comment-help"
                                class="text-caption text-muted-foreground"
                            >
                                ⌘↵ adds it.
                            </p>
                            <SubmitButton
                                class="max-sm:h-11"
                                :processing="processing"
                                >Add Comment</SubmitButton
                            >
                        </div>
                    </Form>
                </section>
            </div>

            <SidePanel aria-label="Progress, Outcome and History">
                <section class="flex flex-col gap-2">
                    <h2 class="text-meta font-medium text-muted-foreground">
                        Progress
                    </h2>
                    <GoalProgressSelect
                        v-if="canChangeProgress"
                        id="goal-progress"
                        :progress="goal.progress"
                        :performance-evaluation-id="evaluation.id"
                        :goal-id="goal.id"
                        label="Progress"
                    />
                    <StatusBadge v-else :state="goal.progress" />
                </section>

                <section v-if="canEdit" class="flex flex-col gap-2">
                    <GoalOutcomeSelect
                        id="goal-outcome"
                        :outcome="goal.outcome"
                        :performance-evaluation-id="evaluation.id"
                        :goal-id="goal.id"
                        :label="goalOutcomeLabel(evaluation.employee.name)"
                    />
                </section>
                <section
                    v-else-if="canSeeOutcomes && goal.outcome"
                    class="flex flex-col gap-1"
                >
                    <h2 class="text-meta font-medium text-muted-foreground">
                        Outcome
                    </h2>
                    <p class="text-body">{{ outcomes[goal.outcome] }}</p>
                </section>

                <section class="flex flex-col gap-3 border-t pt-5">
                    <h2 class="text-meta font-medium text-muted-foreground">
                        History
                    </h2>
                    <ol class="ml-1 flex flex-col gap-3 border-l pl-4">
                        <li
                            v-for="row in shownHistoryRows"
                            :key="row.key"
                            class="relative flex flex-col items-start gap-1"
                        >
                            <span
                                class="absolute top-1.5 -left-[1.3rem] size-2 rounded-full ring-4 ring-surface"
                                :class="progressDotColours[row.progress]"
                                aria-hidden="true"
                            />
                            <StatusBadge :state="row.progress" />
                            <p class="text-caption text-muted-foreground">
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
            </SidePanel>
        </div>

        <template v-if="canEdit">
            <GoalSheet
                :open="editOpen"
                :performance-evaluation-id="evaluation.id"
                :goal="goal"
                :description="evaluationDescription"
                @update:open="setEditOpen"
            />
            <ConfirmDeleteDialog
                v-model:open="confirmingDeletion"
                without-trigger
                :form="GoalController.destroy.form(goalRoute)"
                :title="`Delete “${goal.title}”`"
                :description="goalDeletionWarning(comments.length)"
                confirm-label="Delete Goal"
                :error-bag="`goal-${goal.id}`"
            />
        </template>
    </PageContainer>
</template>

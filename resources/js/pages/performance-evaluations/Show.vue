<script setup lang="ts">
import { Head, Link, setLayoutProps, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Target } from '@lucide/vue';
import GoalController from '@/actions/App/Http/Controllers/Performance/GoalController';
import PerformanceEvaluationAssessmentController from '@/actions/App/Http/Controllers/Performance/PerformanceEvaluationAssessmentController';
import PerformanceEvaluationCompletionController from '@/actions/App/Http/Controllers/Performance/PerformanceEvaluationCompletionController';
import PerformanceEvaluationReopeningController from '@/actions/App/Http/Controllers/Performance/PerformanceEvaluationReopeningController';
import ConfirmDeleteDialog from '@/components/ConfirmDeleteDialog.vue';
import DataTable from '@/components/DataTable.vue';
import EmptyState from '@/components/EmptyState.vue';
import InputError from '@/components/InputError.vue';
import PageContainer from '@/components/PageContainer.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Textarea } from '@/components/ui/textarea';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { formatDate } from '@/lib/dates';
import { outcomes } from '@/lib/outcomes';
import { index as mine } from '@/routes/my-performance-evaluations';
import { index, show } from '@/routes/performance-evaluations';
import {
    create,
    edit,
    show as showGoal,
} from '@/routes/performance-evaluations/goals';
import type {
    AssessedGoal,
    BreadcrumbItem,
    Outcome,
    PerformanceEvaluationRecord,
    TableColumn,
} from '@/types';

/**
 * One Performance Evaluation with its Goals, each with its Progress and a
 * link to its own page. Only the Employee's effective
 * Manager is offered to give, change or remove Goals and to give their
 * Outcomes, the Outcome of the whole and a summary, saving them bit by bit
 * and then Completing it, while it is Open. Anyone else reads the Goals,
 * and the Outcomes only once it is Completed.
 */
const props = defineProps<{
    evaluation: PerformanceEvaluationRecord;
    /** Whether the Performance Evaluation is the viewer's own. */
    isOwn: boolean;
    /** Whether the viewer may give, change or remove its Goals and their Outcomes, and Complete it. */
    canEdit: boolean;
    /** Whether the viewer sees its Outcomes and summary. */
    canSeeOutcomes: boolean;
    /** Whether the viewer may send it, Completed, back to Open for its Manager to correct. */
    canReopen: boolean;
}>();

/** Each Goal's Outcome by Goal ID, the whole's Outcome and the summary, as the Manager is writing them. */
type Assessment = {
    goalOutcomes: Record<number, Outcome | ''>;
    outcome: Outcome | '';
    summary: string;
};

const assessment = useForm<Assessment>({
    goalOutcomes: Object.fromEntries(
        props.evaluation.goals.map((goal) => [goal.id, goal.outcome ?? '']),
    ),
    outcome: props.evaluation.outcome ?? '',
    summary: props.evaluation.summary ?? '',
});

/** The assessment as the server takes it: every Goal on the page, any Outcome still empty as null. */
function assessmentPayload(data: Assessment) {
    return {
        goals: props.evaluation.goals.map((goal) => ({
            id: goal.id,
            outcome: data.goalOutcomes[goal.id] || null,
        })),
        outcome: data.outcome || null,
        summary: data.summary,
    };
}

function saveAssessment(): void {
    assessment
        .transform(assessmentPayload)
        .submit(
            PerformanceEvaluationAssessmentController.update(
                props.evaluation.id,
            ),
            { preserveScroll: true },
        );
}

/** What removing a Goal takes with it, counting its Comments when it has any. */
function removalWarning(goal: AssessedGoal): string {
    const warning =
        'The Goal is removed from this Performance Evaluation for good, with its Progress history.';

    if (goal.comments_count === 0) {
        return warning;
    }

    return `${warning} Its ${goal.comments_count} ${goal.comments_count === 1 ? 'Comment is' : 'Comments are'} removed with it.`;
}

/** Whether the question before Completing is showing. */
const confirmingCompletion = ref(false);

function complete(): void {
    assessment
        .transform(assessmentPayload)
        .submit(
            PerformanceEvaluationCompletionController.store(
                props.evaluation.id,
            ),
            {
                preserveScroll: true,
                onFinish: () => (confirmingCompletion.value = false),
            },
        );
}

/** Performance › year for the Employee; Performance Evaluations › name · year for their Manager. */
const leadingBreadcrumbs: BreadcrumbItem[] = props.isOwn
    ? [{ title: 'Performance', href: mine() }]
    : [
          {
              title: 'Performance Evaluations',
              href: index({ query: { year: String(props.evaluation.year) } }),
          },
      ];

const columns: TableColumn[] = [
    { key: 'goal', label: 'Goal' },
    { key: 'progress', label: 'Progress' },
    ...(props.canSeeOutcomes ? [{ key: 'outcome', label: 'Outcome' }] : []),
    ...(props.canEdit
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
                <div
                    class="flex items-center gap-2 text-sm text-muted-foreground"
                >
                    <StatusBadge :state="evaluation.state" />
                    <span v-if="evaluation.completed_at"
                        >on {{ formatDate(evaluation.completed_at) }}</span
                    >
                </div>
            </div>
            <Button v-if="canEdit" as-child>
                <Link :href="create(evaluation.id)">Give a Goal</Link>
            </Button>
            <ConfirmDeleteDialog
                v-if="canReopen"
                :form="
                    PerformanceEvaluationReopeningController.store.form(
                        evaluation.id,
                    )
                "
                :title="`Reopen ${evaluation.employee.name}'s ${evaluation.year} Performance Evaluation?`"
                :description="`It goes back to Open, keeping its Goals, Outcomes and summary, so their Manager can correct it and Complete it again. ${evaluation.employee.name} doesn't see the Outcomes until then.`"
                confirm-label="Reopen"
                dismiss-label="Keep it Completed"
            >
                <template #trigger>
                    <Button variant="outline">Reopen</Button>
                </template>
            </ConfirmDeleteDialog>
        </header>

        <section class="space-y-2">
            <h2 class="text-sm font-medium">Goals</h2>
            <DataTable :columns="columns" :rows="evaluation.goals">
                <template #cell-goal="{ row }">
                    <Link
                        :href="
                            showGoal({
                                performanceEvaluation: evaluation.id,
                                goal: row.id,
                            })
                        "
                        class="font-medium underline-offset-4 hover:underline"
                        >{{ row.title }}</Link
                    >
                    <p class="whitespace-pre-line text-muted-foreground">
                        {{ row.description }}
                    </p>
                </template>
                <template #cell-progress="{ row }">
                    <StatusBadge :state="row.progress" />
                </template>
                <template #cell-outcome="{ row }">
                    <Select
                        v-if="canEdit"
                        :name="`goals[${row.id}][outcome]`"
                        :model-value="assessment.goalOutcomes[row.id] ?? ''"
                        @update:model-value="
                            assessment.goalOutcomes[row.id] = $event
                                ? ($event as Outcome)
                                : ''
                        "
                    >
                        <SelectTrigger
                            class="w-36"
                            :aria-label="`Outcome of ${row.title}`"
                        >
                            <SelectValue placeholder="Not given" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="(label, outcome) in outcomes"
                                :key="outcome"
                                :value="outcome"
                                >{{ label }}</SelectItem
                            >
                        </SelectContent>
                    </Select>
                    <span v-else-if="row.outcome">{{
                        outcomes[row.outcome]
                    }}</span>
                    <span v-else class="text-muted-foreground">—</span>
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
                            :description="removalWarning(row)"
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
                        <template v-if="canEdit" #action>
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

        <form
            v-if="canEdit && evaluation.goals.length > 0"
            class="space-y-4"
            @submit.prevent="saveAssessment"
        >
            <h2 class="text-sm font-medium">The year as a whole</h2>
            <div class="grid gap-2">
                <Label for="evaluation-outcome">Outcome</Label>
                <Select
                    name="outcome"
                    :model-value="assessment.outcome"
                    @update:model-value="
                        assessment.outcome = $event ? ($event as Outcome) : ''
                    "
                >
                    <SelectTrigger id="evaluation-outcome" class="w-44">
                        <SelectValue placeholder="Not given" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="(label, outcome) in outcomes"
                            :key="outcome"
                            :value="outcome"
                            >{{ label }}</SelectItem
                        >
                    </SelectContent>
                </Select>
                <InputError :message="assessment.errors.outcome" />
            </div>
            <div class="grid gap-2">
                <Label for="evaluation-summary">Summary</Label>
                <Textarea
                    id="evaluation-summary"
                    v-model="assessment.summary"
                    placeholder="How the year went, in a few sentences"
                    maxlength="5000"
                />
                <InputError :message="assessment.errors.summary" />
            </div>
            <p class="text-sm text-muted-foreground">
                {{ evaluation.employee.name }} sees the Outcomes and summary
                once you complete it. After that, it can't change unless it's
                Reopened.
            </p>
            <div class="flex flex-wrap gap-2">
                <SubmitButton
                    variant="outline"
                    :processing="assessment.processing"
                    >Save Outcomes</SubmitButton
                >
                <Dialog v-model:open="confirmingCompletion">
                    <DialogTrigger as-child>
                        <Button type="button">Complete</Button>
                    </DialogTrigger>
                    <DialogContent>
                        <DialogHeader class="space-y-3">
                            <DialogTitle
                                >Complete {{ evaluation.employee.name }}'s
                                {{ evaluation.year }} Performance
                                Evaluation?</DialogTitle
                            >
                            <DialogDescription
                                >{{ evaluation.employee.name }} sees the
                                Outcomes and summary, and it can't change after
                                that unless it's Reopened.</DialogDescription
                            >
                        </DialogHeader>
                        <DialogFooter class="gap-2">
                            <DialogClose as-child>
                                <Button type="button" variant="secondary"
                                    >Keep writing</Button
                                >
                            </DialogClose>
                            <SubmitButton
                                type="button"
                                :processing="assessment.processing"
                                @click="complete"
                                >Complete</SubmitButton
                            >
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            </div>
        </form>

        <section
            v-else-if="canSeeOutcomes && evaluation.outcome"
            class="space-y-2"
        >
            <h2 class="text-sm font-medium">The year as a whole</h2>
            <dl class="grid gap-4 text-sm sm:grid-cols-2">
                <div>
                    <dt class="text-muted-foreground">Outcome</dt>
                    <dd>{{ outcomes[evaluation.outcome] }}</dd>
                </div>
                <div class="sm:col-span-2">
                    <dt class="text-muted-foreground">Summary</dt>
                    <dd class="whitespace-pre-line">
                        {{ evaluation.summary }}
                    </dd>
                </div>
            </dl>
        </section>
    </PageContainer>
</template>

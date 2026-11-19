<script setup lang="ts">
import { Head, Link, setLayoutProps, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { Plus, Target } from '@lucide/vue';
import PerformanceEvaluationAssessmentController from '@/actions/App/Http/Controllers/Performance/PerformanceEvaluationAssessmentController';
import PerformanceEvaluationCompletionController from '@/actions/App/Http/Controllers/Performance/PerformanceEvaluationCompletionController';
import PerformanceEvaluationReopeningController from '@/actions/App/Http/Controllers/Performance/PerformanceEvaluationReopeningController';
import ConfirmDeleteDialog from '@/components/ConfirmDeleteDialog.vue';
import InputError from '@/components/InputError.vue';
import PageContainer from '@/components/PageContainer.vue';
import GoalCard from '@/components/performance-evaluations/GoalCard.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
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
import { getInitials } from '@/composables/useInitials';
import { formatDate } from '@/lib/dates';
import { outcomes } from '@/lib/outcomes';
import { index as mine } from '@/routes/my-performance-evaluations';
import { index, show } from '@/routes/performance-evaluations';
import { create } from '@/routes/performance-evaluations/goals';
import type {
    BreadcrumbItem,
    Outcome,
    PerformanceEvaluationRecord,
} from '@/types';

/**
 * One Performance Evaluation with its Goals as cards, each with its
 * Progress, its Comment count and a link to its own page. The Employee
 * and their Manager change each Goal's Progress from its card while it is
 * Open. Only the Employee's effective Manager is offered to give, change
 * or remove Goals and to give their Outcomes, each saved from its card as
 * it is picked, and the Outcome of the whole and a summary, saved bit by
 * bit, and then Completing it, while it is Open. Anyone else reads the Goals,
 * and the Outcomes only once it is Completed.
 */
const props = defineProps<{
    evaluation: PerformanceEvaluationRecord;
    /** Whether the Performance Evaluation is the viewer's own. */
    isOwn: boolean;
    /** Whether the viewer may give, change or remove its Goals and their Outcomes, and Complete it. */
    canEdit: boolean;
    /** Whether the viewer may change its Goals' Progress. */
    canChangeProgress: boolean;
    /** Whether the viewer sees its Outcomes and summary. */
    canSeeOutcomes: boolean;
    /** Whether the viewer may send it, Completed, back to Open for its Manager to correct. */
    canReopen: boolean;
}>();

/** The whole's Outcome and the summary, as the Manager is writing them. Each Goal's Outcome saves from its card. */
type Assessment = {
    outcome: Outcome | '';
    summary: string;
};

const assessment = useForm<Assessment>({
    outcome: props.evaluation.outcome ?? '',
    summary: props.evaluation.summary ?? '',
});

/** The assessment as the server takes it, an Outcome still empty as null. */
function assessmentPayload(data: Assessment) {
    return {
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

/** Whether the Manager is giving Outcomes, which needs a Goal to assess. */
const isAssessing = computed(
    () => props.canEdit && props.evaluation.goals.length > 0,
);

/** What the Goals' empty state calls the Employee. */
const firstName = props.evaluation.employee.name.trim().split(/\s+/u)[0];

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
        <header class="flex flex-wrap items-center justify-between gap-4">
            <div class="flex min-w-0 items-center gap-4">
                <Avatar class="size-14 rounded-full" aria-hidden="true">
                    <AvatarFallback class="rounded-full text-lg font-medium">{{
                        getInitials(evaluation.employee.name)
                    }}</AvatarFallback>
                </Avatar>
                <div class="flex min-w-0 flex-col gap-1">
                    <h1 class="text-2xl font-semibold tracking-tight">
                        {{ evaluation.employee.name }}
                    </h1>
                    <div
                        class="flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-muted-foreground"
                    >
                        <span
                            >{{ evaluation.year }} Performance Evaluation</span
                        >
                        <span aria-hidden="true">·</span>
                        <StatusBadge :state="evaluation.state" />
                        <span v-if="evaluation.completed_at"
                            >on {{ formatDate(evaluation.completed_at) }}</span
                        >
                    </div>
                </div>
            </div>
            <Button v-if="isAssessing" as-child>
                <Link :href="create(evaluation.id)"><Plus />Give a Goal</Link>
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

        <Card
            v-if="!isAssessing && canSeeOutcomes && evaluation.outcome"
            class="flex-row flex-wrap gap-7 bg-muted/50 p-7 shadow-none"
        >
            <div class="flex shrink-0 flex-col gap-2 sm:w-44">
                <h2
                    class="text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                >
                    The year as a whole
                </h2>
                <p class="text-3xl font-semibold tracking-tight">
                    {{ outcomes[evaluation.outcome] }}
                </p>
            </div>
            <div
                v-if="evaluation.summary"
                class="flex min-w-0 flex-[1_1_20rem] flex-col gap-2"
            >
                <h3
                    class="text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                >
                    Summary
                </h3>
                <p class="text-sm/relaxed whitespace-pre-line">
                    {{ evaluation.summary }}
                </p>
            </div>
        </Card>

        <section class="flex flex-col gap-3.5" aria-labelledby="goals-heading">
            <h2
                id="goals-heading"
                class="text-xs font-semibold tracking-wider text-muted-foreground uppercase"
            >
                Goals<template v-if="evaluation.goals.length > 0">
                    · {{ evaluation.goals.length }}</template
                >
            </h2>

            <template v-if="evaluation.goals.length > 0">
                <GoalCard
                    v-for="(goal, position) in evaluation.goals"
                    :key="goal.id"
                    :goal="goal"
                    :number="position + 1"
                    :performance-evaluation-id="evaluation.id"
                    :can-edit="canEdit"
                    :can-change-progress="canChangeProgress"
                    :can-see-outcomes="canSeeOutcomes"
                />
                <Link
                    v-if="canEdit"
                    :href="create(evaluation.id)"
                    class="flex items-center gap-2.5 rounded-xl border-[1.5px] border-dashed px-6 py-4 text-sm font-medium text-muted-foreground transition-colors hover:border-foreground/40 hover:text-foreground"
                    ><Plus class="size-4" aria-hidden="true" />Give another
                    Goal</Link
                >
            </template>

            <div
                v-else
                class="flex flex-col items-center gap-3.5 rounded-xl border-[1.5px] border-dashed px-6 py-14 text-center"
            >
                <span
                    class="inline-flex size-12 items-center justify-center rounded-xl bg-muted text-muted-foreground"
                >
                    <Target class="size-5.5" aria-hidden="true" />
                </span>
                <div class="flex max-w-sm flex-col gap-1">
                    <p class="font-semibold">No Goals yet</p>
                    <p class="text-sm/normal text-muted-foreground">
                        <template v-if="isOwn"
                            >The Goals your Manager gives you for this year
                            appear here.</template
                        >
                        <template v-else-if="canEdit"
                            >The Goals you give {{ firstName }} for
                            {{ evaluation.year }} appear here.
                            {{ firstName }} sees each one as soon as you give
                            it.</template
                        >
                        <template v-else
                            >The Goals {{ firstName }}'s Manager gives for
                            {{ evaluation.year }} appear here.</template
                        >
                    </p>
                </div>
                <Button v-if="canEdit" as-child class="mt-1.5">
                    <Link :href="create(evaluation.id)"
                        ><Plus />Give the first Goal</Link
                    >
                </Button>
            </div>
        </section>

        <Card v-if="isAssessing" class="p-6">
            <form class="flex flex-col gap-5" @submit.prevent="saveAssessment">
                <div class="flex flex-col gap-1">
                    <h2 class="text-lg font-semibold">The year as a whole</h2>
                    <p class="text-sm text-muted-foreground">
                        {{ evaluation.employee.name }} sees the Outcomes and
                        summary once you complete it. After that, it can't
                        change unless it's Reopened.
                    </p>
                </div>
                <div class="grid gap-2">
                    <Label for="evaluation-outcome">Outcome</Label>
                    <Select
                        name="outcome"
                        :model-value="assessment.outcome"
                        @update:model-value="
                            assessment.outcome = $event
                                ? ($event as Outcome)
                                : ''
                        "
                    >
                        <SelectTrigger id="evaluation-outcome" class="w-48">
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
                        class="min-h-30"
                    />
                    <InputError :message="assessment.errors.summary" />
                </div>
                <div class="flex flex-wrap justify-end gap-2 border-t pt-4">
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
                                    Outcomes and summary, and it can't change
                                    after that unless it's
                                    Reopened.</DialogDescription
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
        </Card>
    </PageContainer>
</template>

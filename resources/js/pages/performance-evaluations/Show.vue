<script setup lang="ts">
import { Head, Link, setLayoutProps, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { Plus } from '@lucide/vue';
import PerformanceEvaluationCompletionController from '@/actions/App/Http/Controllers/Performance/PerformanceEvaluationCompletionController';
import PerformanceEvaluationReopeningController from '@/actions/App/Http/Controllers/Performance/PerformanceEvaluationReopeningController';
import PerformanceEvaluationSummaryController from '@/actions/App/Http/Controllers/Performance/PerformanceEvaluationSummaryController';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import InputError from '@/components/InputError.vue';
import Heading from '@/components/Heading.vue';
import PageContainer from '@/components/PageContainer.vue';
import PerformanceEvaluationBody from '@/components/performance-evaluations/PerformanceEvaluationBody.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import SubmitButton from '@/components/SubmitButton.vue';
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
 * it is picked, and to write a summary, saved with its button, while it
 * is Open. Once every Goal has an Outcome and the summary is written,
 * giving the whole its Outcome Completes it, after asking. Anyone else reads the Goals,
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

/** The summary, saved with its button. */
const summaryForm = useForm({ summary: props.evaluation.summary ?? '' });

function saveSummary(): void {
    summaryForm.submit(
        PerformanceEvaluationSummaryController.update(props.evaluation.id),
        { preserveScroll: true },
    );
}

/** Whether every Goal has an Outcome and the summary is written, as saved, so giving the whole its Outcome may Complete it. */
const isReadyToComplete = computed(
    () =>
        props.evaluation.goals.every((goal) => goal.outcome !== null) &&
        Boolean(props.evaluation.summary?.trim()),
);

/** The Outcome of the whole that Completing sends. */
const completion = useForm<{ outcome: Outcome | '' }>({ outcome: '' });

/**
 * What the Outcome dropdown holds: empty, with the kept Outcome after a
 * Reopen showing in its place, except while the question before
 * Completing asks about the one picked. Keeping it empty lets picking the
 * kept Outcome again still ask.
 */
const pickedOutcome = ref<Outcome | ''>('');

/** Whether the question before Completing is showing. */
const confirmingCompletion = ref(false);

watch(confirmingCompletion, (isShowing) => {
    if (!isShowing) {
        pickedOutcome.value = '';
    }
});

function askToComplete(outcome: Outcome): void {
    pickedOutcome.value = outcome;
    completion.outcome = outcome;
    confirmingCompletion.value = true;
}

function complete(): void {
    completion.submit(
        PerformanceEvaluationCompletionController.store(props.evaluation.id),
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

const title = props.isOwn
    ? String(props.evaluation.year)
    : `${props.evaluation.employee.name} · ${props.evaluation.year}`;

/** The page's title: whose Evaluation, and for which year. */
const heading = props.isOwn
    ? `Your ${props.evaluation.year} Performance Evaluation`
    : `${props.evaluation.employee.name}'s ${props.evaluation.year} Performance Evaluation`;

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
        <Heading :title="heading">
            <template #description>
                <StatusBadge :state="evaluation.state" />
                <span v-if="evaluation.completed_at"
                    >on {{ formatDate(evaluation.completed_at) }}</span
                >
            </template>
            <template v-if="isAssessing || canReopen" #actions>
                <Button v-if="isAssessing" as-child>
                    <Link :href="create(evaluation.id)"
                        ><Plus />Give a Goal</Link
                    >
                </Button>
                <ConfirmDialog
                    v-if="canReopen"
                    :form="
                        PerformanceEvaluationReopeningController.store.form(
                            evaluation.id,
                        )
                    "
                    :title="`Reopen ${evaluation.employee.name}'s ${evaluation.year} Performance Evaluation?`"
                    :description="`It goes back to Open so it can be corrected. Its Goals, Outcomes and summary stay, but ${evaluation.employee.name} won't see the Outcomes until it's Completed again.`"
                    confirm-label="Reopen"
                    dismiss-label="Keep it Completed"
                >
                    <template #trigger>
                        <Button variant="outline">Reopen</Button>
                    </template>
                </ConfirmDialog>
            </template>
        </Heading>

        <PerformanceEvaluationBody
            :evaluation="evaluation"
            :is-own="isOwn"
            :can-edit="canEdit"
            :can-change-progress="canChangeProgress"
            :can-see-outcomes="canSeeOutcomes"
        />

        <Card v-if="isAssessing" class="flex flex-col gap-5 p-6">
            <div class="flex flex-col gap-1">
                <h2 class="text-lg font-semibold">The year as a whole</h2>
                <p class="text-sm text-muted-foreground">
                    Giving the year its Outcome completes it:
                    {{ evaluation.employee.name }} then sees the Outcomes and
                    summary, and it can't change unless it's Reopened.
                </p>
            </div>
            <form class="grid gap-2" @submit.prevent="saveSummary">
                <Label for="evaluation-summary">Summary</Label>
                <Textarea
                    id="evaluation-summary"
                    v-model="summaryForm.summary"
                    placeholder="How the year went, in a few sentences"
                    maxlength="5000"
                    class="min-h-30"
                />
                <InputError :message="summaryForm.errors.summary" />
                <div>
                    <SubmitButton
                        variant="outline"
                        :processing="summaryForm.processing"
                        :disabled="!summaryForm.isDirty"
                        >Save summary</SubmitButton
                    >
                </div>
            </form>
            <div class="grid gap-2">
                <Label for="evaluation-outcome">Outcome</Label>
                <Select
                    name="outcome"
                    required
                    :model-value="pickedOutcome"
                    :disabled="!isReadyToComplete || completion.processing"
                    @update:model-value="askToComplete($event as Outcome)"
                >
                    <SelectTrigger
                        id="evaluation-outcome"
                        class="w-48"
                        :class="{
                            'data-[placeholder]:text-foreground':
                                evaluation.outcome,
                        }"
                    >
                        <SelectValue
                            :placeholder="
                                evaluation.outcome
                                    ? outcomes[evaluation.outcome]
                                    : 'Not given'
                            "
                        />
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
                <p
                    v-if="!isReadyToComplete"
                    class="text-sm text-muted-foreground"
                >
                    Give every Goal an Outcome and write the summary to complete
                    it.
                </p>
                <InputError :message="completion.errors.outcome" />
            </div>
            <Dialog v-model:open="confirmingCompletion">
                <DialogContent>
                    <DialogHeader class="space-y-3">
                        <DialogTitle
                            >Complete {{ evaluation.employee.name }}'s
                            {{ evaluation.year }} Performance Evaluation as
                            {{
                                completion.outcome
                                    ? outcomes[completion.outcome]
                                    : ''
                            }}?</DialogTitle
                        >
                        <DialogDescription
                            >{{ evaluation.employee.name }} sees the Outcomes
                            and summary.</DialogDescription
                        >
                    </DialogHeader>
                    <DialogFooter class="gap-2">
                        <DialogClose as-child>
                            <Button type="button" variant="secondary"
                                >Keep it Open</Button
                            >
                        </DialogClose>
                        <SubmitButton
                            type="button"
                            :processing="completion.processing"
                            @click="complete"
                            >Complete</SubmitButton
                        >
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </Card>
    </PageContainer>
</template>

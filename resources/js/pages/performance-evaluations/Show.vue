<script setup lang="ts">
import { Head, Link, setLayoutProps, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { Plus } from '@lucide/vue';
import PerformanceEvaluationCompletionController from '@/actions/App/Http/Controllers/Performance/PerformanceEvaluationCompletionController';
import PerformanceEvaluationReopeningController from '@/actions/App/Http/Controllers/Performance/PerformanceEvaluationReopeningController';
import PerformanceEvaluationSummaryController from '@/actions/App/Http/Controllers/Performance/PerformanceEvaluationSummaryController';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import InputError from '@/components/InputError.vue';
import PageContainer from '@/components/PageContainer.vue';
import PageHeader from '@/components/PageHeader.vue';
import GoalSheet from '@/components/performance-evaluations/GoalSheet.vue';
import PerformanceEvaluationBody from '@/components/performance-evaluations/PerformanceEvaluationBody.vue';
import SidePanel from '@/components/SidePanel.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { firstName } from '@/composables/useInitials';
import { useOverlayRoute } from '@/composables/useOverlayRoute';
import { formatDate } from '@/lib/dates';
import { goalOutcomeLabel } from '@/lib/goals';
import { outcomes } from '@/lib/outcomes';
import { index as mine } from '@/routes/my-performance-evaluations';
import { index, show } from '@/routes/performance-evaluations';
import { create } from '@/routes/performance-evaluations/goals';
import type {
    BreadcrumbItem,
    Outcome,
    PerformanceEvaluationRecord,
} from '@/types';

const props = defineProps<{
    evaluation: PerformanceEvaluationRecord;
    isOwn: boolean;
    canEdit: boolean;
    canChangeProgress: boolean;
    canSeeOutcomes: boolean;
    canReopen: boolean;
    addingGoal?: boolean;
}>();

const { open: addGoalOpen, setOpen: setAddGoalOpen } = useOverlayRoute(
    () => props.addingGoal === true,
    () => show(props.evaluation.id).url,
);

const summaryForm = useForm({ summary: props.evaluation.summary ?? '' });

watch(
    () => props.evaluation.summary,
    (saved) => summaryForm.defaults({ summary: saved ?? '' }),
);

function saveSummary(): void {
    summaryForm.submit(
        PerformanceEvaluationSummaryController.update(props.evaluation.id),
        { preserveScroll: true },
    );
}

/** The Outcome of the whole that Complete sends: the one kept after a Reopen, until another is picked. */
const pickedOutcome = ref<Outcome | ''>(props.evaluation.outcome ?? '');

const goalsAndSummaryReady = computed(
    () =>
        props.evaluation.goals.every((goal) => goal.outcome !== null) &&
        Boolean(props.evaluation.summary?.trim()),
);

const notReadyReason = computed(() => {
    /** Deliberately stricter than the server: Completing sends no summary, so it would Complete with the old saved one. */
    if (summaryForm.isDirty) {
        return 'Save the summary first to Complete it.';
    }

    if (!goalsAndSummaryReady.value) {
        return 'Pick an Outcome for every Goal and save the summary to Complete it.';
    }

    if (pickedOutcome.value === '') {
        return 'Pick the Outcome of the year as a whole to Complete it.';
    }

    return null;
});

const leadingBreadcrumbs: BreadcrumbItem[] = props.isOwn
    ? [{ title: 'Performance', href: mine() }]
    : [
          {
              title: 'Performance Evaluations',
              href: index({ query: { year: String(props.evaluation.year) } }),
          },
      ];

const isAssessing = computed(
    () => props.canEdit && props.evaluation.goals.length > 0,
);

const employeeFirstName = computed(() =>
    firstName(props.evaluation.employee.name),
);

const title = props.isOwn
    ? String(props.evaluation.year)
    : `${props.evaluation.employee.name} · ${props.evaluation.year}`;

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

    <PageContainer width="full">
        <PageHeader :title="heading">
            <template #status>
                <StatusBadge :state="evaluation.state" />
            </template>
            <template v-if="evaluation.completed_at" #description>
                <span
                    >Completed on
                    <span class="tabular-nums">{{
                        formatDate(evaluation.completed_at)
                    }}</span></span
                >
            </template>
            <template v-if="isAssessing || canReopen" #actions>
                <Button v-if="isAssessing" variant="secondary" as-child>
                    <Link
                        :href="create(evaluation.id)"
                        preserve-scroll
                        preserve-state
                        ><Plus />Add Goal</Link
                    >
                </Button>
                <ConfirmDialog
                    v-if="canReopen"
                    :form="
                        PerformanceEvaluationReopeningController.store.form(
                            evaluation.id,
                        )
                    "
                    :title="`Reopen ${evaluation.employee.name}'s ${evaluation.year} Performance Evaluation`"
                    :description="`It goes back to Open so it can be corrected. Its Goals, Outcomes and summary stay, but ${employeeFirstName} won't see the Outcomes until it's Completed again.`"
                    confirm-label="Reopen Performance Evaluation"
                    dismiss-label="Keep it Completed"
                >
                    <template #trigger>
                        <Button variant="secondary">Reopen…</Button>
                    </template>
                </ConfirmDialog>
            </template>
        </PageHeader>

        <div class="grid items-start gap-8 xl:grid-cols-[minmax(0,1fr)_22rem]">
            <PerformanceEvaluationBody
                :evaluation="evaluation"
                :is-own="isOwn"
                :can-edit="canEdit"
                :can-change-progress="canChangeProgress"
                :can-see-outcomes="canSeeOutcomes"
                :is-assessing="isAssessing"
                :employee-first-name="employeeFirstName"
            />

            <SidePanel v-if="isAssessing" aria-labelledby="year-as-a-whole">
                <div class="flex flex-col gap-1">
                    <h2 id="year-as-a-whole" class="text-section">
                        Year as a whole
                    </h2>
                    <p class="text-caption text-muted-foreground">
                        Completing it shows {{ employeeFirstName }} the Outcomes
                        and summary. It can't change after that unless it's
                        Reopened.
                    </p>
                </div>

                <div class="grid gap-2">
                    <Label for="evaluation-outcome">{{
                        goalOutcomeLabel(evaluation.employee.name)
                    }}</Label>
                    <Select v-model="pickedOutcome" name="outcome" required>
                        <SelectTrigger
                            id="evaluation-outcome"
                            class="w-full max-sm:h-11"
                        >
                            <SelectValue placeholder="—" />
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
                </div>

                <form class="grid gap-2" @submit.prevent="saveSummary">
                    <Label for="evaluation-summary">Summary</Label>
                    <Textarea
                        id="evaluation-summary"
                        v-model="summaryForm.summary"
                        placeholder="e.g. A strong year: the Checkout API shipped early."
                        maxlength="5000"
                        class="min-h-30"
                    />
                    <InputError :message="summaryForm.errors.summary" />
                    <div>
                        <SubmitButton
                            variant="secondary"
                            class="max-sm:h-11"
                            :processing="summaryForm.processing"
                            :disabled="!summaryForm.isDirty"
                            >Save summary</SubmitButton
                        >
                    </div>
                </form>

                <div class="flex flex-col gap-2 border-t pt-5">
                    <Tooltip v-if="notReadyReason">
                        <TooltipTrigger as-child>
                            <span tabindex="0" class="flex">
                                <Button
                                    type="button"
                                    class="w-full max-sm:h-11"
                                    disabled
                                    >Complete…</Button
                                >
                            </span>
                        </TooltipTrigger>
                        <TooltipContent>{{ notReadyReason }}</TooltipContent>
                    </Tooltip>
                    <ConfirmDialog
                        v-else
                        :form="
                            PerformanceEvaluationCompletionController.store.form(
                                evaluation.id,
                            )
                        "
                        :title="`Complete ${evaluation.employee.name}'s ${evaluation.year} Performance Evaluation as ${pickedOutcome ? outcomes[pickedOutcome] : ''}`"
                        :description="`${employeeFirstName} sees the Outcomes and summary. It can't change after that unless it's Reopened.`"
                        confirm-label="Complete Performance Evaluation"
                        dismiss-label="Keep it Open"
                    >
                        <input
                            type="hidden"
                            name="outcome"
                            :value="pickedOutcome"
                        />
                        <template #trigger>
                            <Button type="button" class="w-full max-sm:h-11"
                                >Complete…</Button
                            >
                        </template>
                    </ConfirmDialog>
                    <p
                        v-if="notReadyReason"
                        class="text-caption text-muted-foreground"
                    >
                        {{ notReadyReason }}
                    </p>
                </div>
            </SidePanel>
        </div>

        <GoalSheet
            v-if="canEdit"
            :open="addGoalOpen"
            :performance-evaluation-id="evaluation.id"
            :description="heading"
            @update:open="setAddGoalOpen"
        />
    </PageContainer>
</template>

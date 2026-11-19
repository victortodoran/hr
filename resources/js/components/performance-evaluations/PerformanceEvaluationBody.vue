<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Plus } from '@lucide/vue';
import GoalCard from '@/components/performance-evaluations/GoalCard.vue';
import NoGoalsYet from '@/components/performance-evaluations/NoGoalsYet.vue';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { outcomes } from '@/lib/outcomes';
import { create } from '@/routes/performance-evaluations/goals';
import type { PerformanceEvaluationRecord } from '@/types';

/**
 * A Performance Evaluation's Goals as cards, under the Outcome of the
 * year as a whole and its summary once the viewer may see them, as both
 * its own page and the Employee's Performance page show it. Whoever may
 * edit also gives Goals from here.
 */
const props = defineProps<{
    evaluation: PerformanceEvaluationRecord;
    /** Whether the Performance Evaluation is the viewer's own. */
    isOwn: boolean;
    /** Whether the viewer may give, change or remove its Goals and their Outcomes. */
    canEdit: boolean;
    /** Whether the viewer may change its Goals' Progress. */
    canChangeProgress: boolean;
    /** Whether the viewer sees its Outcomes and summary. */
    canSeeOutcomes: boolean;
}>();

/** Whether the Manager is giving Outcomes, which their own card below the Goals does instead. */
const isAssessing = computed(
    () => props.canEdit && props.evaluation.goals.length > 0,
);

/** What the Goals' empty state calls the Employee. */
const firstName = computed(
    () => props.evaluation.employee.name.trim().split(/\s+/u)[0],
);
</script>

<template>
    <div class="flex flex-col gap-6">
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

            <NoGoalsYet v-else>
                <template v-if="isOwn"
                    >The Goals your Manager gives you for this year appear
                    here.</template
                >
                <template v-else-if="canEdit"
                    >The Goals you give {{ firstName }} for
                    {{ evaluation.year }} appear here. {{ firstName }} sees each
                    one as soon as you give it.</template
                >
                <template v-else
                    >The Goals {{ firstName }}'s Manager gives for
                    {{ evaluation.year }} appear here.</template
                >
                <template v-if="canEdit" #action>
                    <Button as-child class="mt-1.5">
                        <Link :href="create(evaluation.id)"
                            ><Plus />Give the first Goal</Link
                        >
                    </Button>
                </template>
            </NoGoalsYet>
        </section>
    </div>
</template>

<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Plus } from '@lucide/vue';
import GoalCard from '@/components/performance-evaluations/GoalCard.vue';
import NoGoalsYet from '@/components/performance-evaluations/NoGoalsYet.vue';
import StatStrip from '@/components/StatStrip.vue';
import { Button } from '@/components/ui/button';
import { outcomes } from '@/lib/outcomes';
import { create } from '@/routes/performance-evaluations/goals';
import type { PerformanceEvaluationRecord } from '@/types';

const props = defineProps<{
    evaluation: PerformanceEvaluationRecord;
    isOwn: boolean;
    canEdit: boolean;
    canChangeProgress: boolean;
    canSeeOutcomes: boolean;
    isAssessing: boolean;
    employeeFirstName: string;
}>();

const stats = computed(() =>
    props.evaluation.outcome
        ? [
              { label: 'Outcome', value: outcomes[props.evaluation.outcome] },
              {
                  label: 'Goals',
                  value: String(props.evaluation.goals.length),
              },
          ]
        : [],
);
</script>

<template>
    <div class="flex flex-col gap-8">
        <template v-if="!isAssessing && canSeeOutcomes && evaluation.outcome">
            <StatStrip :stats="stats" class="sm:w-fit" />
            <section
                v-if="evaluation.summary"
                class="flex flex-col gap-2"
                aria-labelledby="summary-heading"
            >
                <h2 id="summary-heading" class="text-section">Summary</h2>
                <p class="max-w-prose text-body whitespace-pre-line">
                    {{ evaluation.summary }}
                </p>
            </section>
        </template>

        <section class="flex flex-col gap-3" aria-labelledby="goals-heading">
            <h2 id="goals-heading" class="text-section">
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
                    :employee-name="evaluation.employee.name"
                    :can-edit="canEdit"
                    :can-change-progress="canChangeProgress"
                    :can-see-outcomes="canSeeOutcomes"
                />
            </template>

            <NoGoalsYet v-else>
                <template v-if="isOwn"
                    >The Goals your Manager adds for this year appear
                    here.</template
                >
                <template v-else-if="canEdit"
                    >The Goals you add for {{ employeeFirstName }} for
                    {{ evaluation.year }} appear here.
                    {{ employeeFirstName }} sees each one as soon as you add
                    it.</template
                >
                <template v-else
                    >The Goals {{ employeeFirstName }}'s Manager adds for
                    {{ evaluation.year }} appear here.</template
                >
                <template v-if="canEdit" #action>
                    <Button as-child class="mt-1.5 max-sm:h-11">
                        <Link
                            :href="create(evaluation.id)"
                            preserve-scroll
                            preserve-state
                            ><Plus />Add Goal</Link
                        >
                    </Button>
                </template>
            </NoGoalsYet>
        </section>
    </div>
</template>

<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { MessageSquare } from '@lucide/vue';
import GoalController from '@/actions/App/Http/Controllers/Performance/GoalController';
import ConfirmDeleteDialog from '@/components/ConfirmDeleteDialog.vue';
import GoalOutcomeSelect from '@/components/performance-evaluations/GoalOutcomeSelect.vue';
import GoalProgressSelect from '@/components/performance-evaluations/GoalProgressSelect.vue';
import RowActions from '@/components/RowActions.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Card } from '@/components/ui/card';
import {
    DropdownMenuItem,
    DropdownMenuSeparator,
} from '@/components/ui/dropdown-menu';
import { goalDeletionWarning, goalOutcomeLabel } from '@/lib/goals';
import { outcomes } from '@/lib/outcomes';
import { show } from '@/routes/performance-evaluations/goals';
import type { AssessedGoal } from '@/types';

/**
 * One Goal on its Performance Evaluation's page (rules L9, K1): its
 * number, its title linking to its own page, its description, its
 * Progress as a StatusBadge and how many Comments it has. The Employee and
 * their Manager change its Progress here while it is Open, and whoever may
 * edit gives, changes or clears its Outcome, a plain Select (S11); both
 * save as soon as they are picked. Whoever may edit also, from its ⋯ menu
 * (K2), edits it on its own page with the Edit Goal sheet open, or deletes
 * it after confirming (X3); anyone else who may see Outcomes reads the one
 * given as plain text.
 */
const props = defineProps<{
    goal: AssessedGoal;
    /** Its place in the list, counting from 1. */
    number: number;
    performanceEvaluationId: number;
    /** Whose Performance Evaluation it is, as its Outcome field names them. */
    employeeName: string;
    /** Whether the viewer may edit or delete it and give its Outcome. */
    canEdit: boolean;
    /** Whether the viewer may change its Progress. */
    canChangeProgress: boolean;
    /** Whether the viewer sees its Outcome. */
    canSeeOutcomes: boolean;
}>();

/** Whether the confirmation before deleting it is showing. */
const confirmingDeletion = ref(false);

const goalRoute = computed(() => ({
    performanceEvaluation: props.performanceEvaluationId,
    goal: props.goal.id,
}));

/** "No Comments", "1 Comment" or "3 Comments". */
const commentCountLabel = computed(() => {
    const count = props.goal.comments_count;

    if (count === 0) {
        return 'No Comments';
    }

    return `${count} ${count === 1 ? 'Comment' : 'Comments'}`;
});
</script>

<template>
    <Card class="group/row flex-row gap-4 px-6 py-5 max-sm:px-4">
        <span
            class="w-6 shrink-0 pt-0.5 text-body font-semibold text-muted-foreground tabular-nums"
            aria-hidden="true"
            >{{ String(number).padStart(2, '0') }}</span
        >
        <div class="flex min-w-0 flex-1 flex-col">
            <div class="flex items-start justify-between gap-3">
                <h3 class="text-section">
                    <Link
                        :href="show(goalRoute)"
                        class="underline-offset-4 hover:underline"
                        >{{ goal.title }}</Link
                    >
                </h3>
                <RowActions v-if="canEdit" :label="goal.title" class="-mt-1">
                    <template #menu>
                        <DropdownMenuItem as-child>
                            <Link
                                :href="
                                    show(goalRoute, {
                                        query: { editing: '1' },
                                    })
                                "
                                >Edit</Link
                            >
                        </DropdownMenuItem>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem
                            variant="destructive"
                            @select="confirmingDeletion = true"
                            >Delete Goal…</DropdownMenuItem
                        >
                    </template>
                </RowActions>
            </div>
            <p class="mt-1 text-body whitespace-pre-line text-muted-foreground">
                {{ goal.description }}
            </p>
            <div
                class="mt-4 flex flex-wrap items-center justify-between gap-3 border-t pt-3"
            >
                <div
                    class="flex items-center gap-4 text-meta text-muted-foreground"
                >
                    <GoalProgressSelect
                        v-if="canChangeProgress"
                        :progress="goal.progress"
                        :performance-evaluation-id="performanceEvaluationId"
                        :goal-id="goal.id"
                        :label="`Progress of ${goal.title}`"
                    />
                    <StatusBadge v-else :state="goal.progress" />
                    <span class="inline-flex items-center gap-1.5">
                        <MessageSquare class="size-3.5" aria-hidden="true" />
                        {{ commentCountLabel }}
                    </span>
                </div>
                <GoalOutcomeSelect
                    v-if="canEdit"
                    :id="`goal-${goal.id}-outcome`"
                    :outcome="goal.outcome"
                    :performance-evaluation-id="performanceEvaluationId"
                    :goal-id="goal.id"
                    :label="goalOutcomeLabel(employeeName)"
                    :accessible-name="`Outcome of ${goal.title}`"
                />
                <p v-else-if="canSeeOutcomes && goal.outcome" class="text-meta">
                    <span class="text-muted-foreground">Outcome:</span>
                    {{ outcomes[goal.outcome] }}
                </p>
            </div>
        </div>
        <ConfirmDeleteDialog
            v-if="canEdit"
            v-model:open="confirmingDeletion"
            without-trigger
            :form="GoalController.destroy.form(goalRoute)"
            :title="`Delete “${goal.title}”`"
            :description="goalDeletionWarning(goal.comments_count)"
            confirm-label="Delete Goal"
            :error-bag="`goal-${goal.id}`"
        />
    </Card>
</template>

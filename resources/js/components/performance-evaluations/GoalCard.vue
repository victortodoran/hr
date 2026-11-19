<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { MessageSquare, MoreHorizontal } from '@lucide/vue';
import GoalController from '@/actions/App/Http/Controllers/Performance/GoalController';
import ConfirmDeleteDialog from '@/components/ConfirmDeleteDialog.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { goalRemovalWarning } from '@/lib/goals';
import { outcomes } from '@/lib/outcomes';
import { edit, show } from '@/routes/performance-evaluations/goals';
import type { AssessedGoal, Outcome } from '@/types';

/**
 * One Goal on its Performance Evaluation's page: its number, its title
 * linking to its own page, its description, its Progress and how many
 * Comments it has. Whoever may edit gives its Outcome here and, from its
 * menu, changes or removes it; anyone else who may see Outcomes reads the
 * one given.
 */
const props = defineProps<{
    goal: AssessedGoal;
    /** Its place in the list, counting from 1. */
    number: number;
    performanceEvaluationId: number;
    /** Whether the viewer may change or remove it and give its Outcome. */
    canEdit: boolean;
    /** Whether the viewer sees its Outcome. */
    canSeeOutcomes: boolean;
}>();

/** The Outcome the Manager is giving it, empty while not given. */
const outcome = defineModel<Outcome | ''>('outcome', { default: '' });

/** Whether the question before removing it is showing. */
const confirmingRemoval = ref(false);

const goalRoute = computed(() => ({
    performanceEvaluation: props.performanceEvaluationId,
    goal: props.goal.id,
}));

/** "No comments", "1 comment" or "3 comments". */
const commentCountLabel = computed(() => {
    const count = props.goal.comments_count;

    if (count === 0) {
        return 'No comments';
    }

    return `${count} ${count === 1 ? 'comment' : 'comments'}`;
});
</script>

<template>
    <Card class="flex-row gap-4 px-6 py-5">
        <span
            class="w-6 shrink-0 pt-0.5 text-sm font-semibold text-muted-foreground/70 tabular-nums"
            aria-hidden="true"
            >{{ String(number).padStart(2, '0') }}</span
        >
        <div class="flex min-w-0 flex-1 flex-col">
            <div class="flex items-start justify-between gap-3">
                <h3 class="font-semibold tracking-tight">
                    <Link
                        :href="show(goalRoute)"
                        class="underline-offset-4 hover:underline"
                        >{{ goal.title }}</Link
                    >
                </h3>
                <DropdownMenu v-if="canEdit">
                    <DropdownMenuTrigger as-child>
                        <Button
                            variant="ghost"
                            size="icon"
                            class="-mt-1 size-8 shrink-0"
                            :aria-label="`More for ${goal.title}`"
                        >
                            <MoreHorizontal />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" class="w-40">
                        <DropdownMenuItem as-child>
                            <Link
                                :href="edit(goalRoute)"
                                :aria-label="`Change ${goal.title}`"
                                >Change</Link
                            >
                        </DropdownMenuItem>
                        <DropdownMenuItem
                            variant="destructive"
                            :aria-label="`Remove ${goal.title}`"
                            @select="confirmingRemoval = true"
                            >Remove</DropdownMenuItem
                        >
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>
            <p
                class="mt-1.5 text-sm/relaxed whitespace-pre-line text-muted-foreground"
            >
                {{ goal.description }}
            </p>
            <div
                class="mt-4 flex flex-wrap items-center justify-between gap-3 border-t pt-3.5"
            >
                <div
                    class="flex items-center gap-3.5 text-sm text-muted-foreground"
                >
                    <StatusBadge :state="goal.progress" />
                    <span class="inline-flex items-center gap-1.5">
                        <MessageSquare class="size-3.5" aria-hidden="true" />
                        {{ commentCountLabel }}
                    </span>
                </div>
                <div v-if="canEdit" class="flex items-center gap-2">
                    <span
                        class="text-sm text-muted-foreground"
                        aria-hidden="true"
                        >Outcome</span
                    >
                    <Select
                        :name="`goals[${goal.id}][outcome]`"
                        :model-value="outcome"
                        @update:model-value="
                            outcome = $event ? ($event as Outcome) : ''
                        "
                    >
                        <SelectTrigger
                            class="w-36"
                            :aria-label="`Outcome of ${goal.title}`"
                        >
                            <SelectValue placeholder="Not given" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="(label, value) in outcomes"
                                :key="value"
                                :value="value"
                                >{{ label }}</SelectItem
                            >
                        </SelectContent>
                    </Select>
                </div>
                <span
                    v-else-if="canSeeOutcomes && goal.outcome"
                    class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-0.5 text-xs font-medium"
                    ><span class="font-normal text-muted-foreground"
                        >Outcome</span
                    >{{ outcomes[goal.outcome] }}</span
                >
            </div>
        </div>
        <ConfirmDeleteDialog
            v-if="canEdit"
            v-model:open="confirmingRemoval"
            without-trigger
            :form="GoalController.destroy.form(goalRoute)"
            :title="`Remove “${goal.title}”?`"
            :description="goalRemovalWarning(goal.comments_count)"
            confirm-label="Remove Goal"
            :error-bag="`goal-${goal.id}`"
        />
    </Card>
</template>

<script setup lang="ts">
import GoalOutcomeController from '@/actions/App/Http/Controllers/Performance/GoalOutcomeController';
import SavedTick from '@/components/SavedTick.vue';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useSaveOnPick } from '@/composables/useSaveOnPick';
import { outcomes } from '@/lib/outcomes';
import { statusPillTrigger } from '@/lib/statusMeta';
import type { Outcome } from '@/types';

/**
 * A Goal's Outcome as a pill that gives, changes or clears it ("Not
 * given") as soon as one is picked, with a tick once saved. Dark once
 * given, grey while not.
 */
const props = defineProps<{
    /** The Outcome given so far, if any. */
    outcome: Outcome | null;
    performanceEvaluationId: number;
    goalId: number;
    /** What the dropdown is called for screen readers. */
    label: string;
}>();

/** The dropdown's choice for none, which clears it. */
const notGiven = 'not-given';

const { chosen, saving, justSaved, save } = useSaveOnPick<Outcome | null>({
    saved: () => props.outcome,
    url: () =>
        GoalOutcomeController.update.url({
            performanceEvaluation: props.performanceEvaluationId,
            goal: props.goalId,
        }),
    field: 'outcome',
    label: 'Outcome',
});
</script>

<template>
    <div class="flex items-center gap-2">
        <Select
            :model-value="chosen ?? notGiven"
            :disabled="saving"
            @update:model-value="
                save($event === notGiven ? null : ($event as Outcome))
            "
        >
            <SelectTrigger
                size="sm"
                :aria-label="label"
                :class="[
                    statusPillTrigger,
                    chosen
                        ? 'bg-primary text-primary-foreground'
                        : 'bg-status-neutral text-status-neutral-foreground',
                ]"
            >
                <SelectValue />
            </SelectTrigger>
            <SelectContent>
                <SelectItem :value="notGiven">Not given</SelectItem>
                <SelectItem
                    v-for="(name, value) in outcomes"
                    :key="value"
                    :value="value"
                    >{{ name }}</SelectItem
                >
            </SelectContent>
        </Select>
        <SavedTick :shown="justSaved" />
    </div>
</template>

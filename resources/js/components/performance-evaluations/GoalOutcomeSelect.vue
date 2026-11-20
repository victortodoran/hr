<script setup lang="ts">
import GoalOutcomeController from '@/actions/App/Http/Controllers/Performance/GoalOutcomeController';
import SavedTick from '@/components/SavedTick.vue';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useSaveOnPick } from '@/composables/useSaveOnPick';
import { outcomes } from '@/lib/outcomes';
import type { Outcome } from '@/types';

/** A plain Select, not a badge: an Outcome is a rating, not a state (S11). */
const props = defineProps<{
    outcome: Outcome | null;
    performanceEvaluationId: number;
    goalId: number;
    id: string;
    label: string;
    accessibleName?: string;
}>();

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
    <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
        <Label :for="id" class="text-meta font-normal text-muted-foreground">{{
            label
        }}</Label>
        <div class="flex items-center gap-2">
            <Select
                :model-value="chosen ?? notGiven"
                :disabled="saving"
                @update:model-value="
                    save($event === notGiven ? null : ($event as Outcome))
                "
            >
                <SelectTrigger
                    :id="id"
                    size="sm"
                    :aria-label="accessibleName"
                    class="min-w-32 max-sm:h-11"
                >
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem :value="notGiven">—</SelectItem>
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
    </div>
</template>

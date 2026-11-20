<script setup lang="ts">
import GoalProgressController from '@/actions/App/Http/Controllers/Performance/GoalProgressController';
import SavedTick from '@/components/SavedTick.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
} from '@/components/ui/select';
import { useSaveOnPick } from '@/composables/useSaveOnPick';
import { progresses } from '@/lib/progress';
import type { Progress } from '@/types';

/**
 * A Goal's Progress as a Select whose trigger is the Progress's own
 * StatusBadge (rules S6, F6), saved as soon as one is picked, with a tick
 * once saved. On a phone the trigger is a 44px target (P6).
 */
const props = defineProps<{
    /** The Progress as saved. */
    progress: Progress;
    performanceEvaluationId: number;
    goalId: number;
    /** What the dropdown is called for screen readers, such as "Progress of Lead the API". */
    label: string;
    /** The trigger's id, when something on the page points at it. */
    id?: string;
}>();

const { chosen, saving, justSaved, save } = useSaveOnPick<Progress>({
    saved: () => props.progress,
    url: () =>
        GoalProgressController.update.url({
            performanceEvaluation: props.performanceEvaluationId,
            goal: props.goalId,
        }),
    field: 'progress',
    label: 'Progress',
});
</script>

<template>
    <div class="flex items-center gap-2">
        <Select
            :model-value="chosen"
            :disabled="saving"
            @update:model-value="save($event as Progress)"
        >
            <SelectTrigger
                :id="id"
                size="sm"
                :aria-label="label"
                class="gap-1 rounded-full border-0 bg-transparent p-0 shadow-none data-[size=sm]:h-auto max-sm:data-[size=sm]:h-11"
            >
                <StatusBadge :state="chosen" />
            </SelectTrigger>
            <SelectContent>
                <SelectItem
                    v-for="(name, value) in progresses"
                    :key="value"
                    :value="value"
                    >{{ name }}</SelectItem
                >
            </SelectContent>
        </Select>
        <SavedTick :shown="justSaved" />
    </div>
</template>

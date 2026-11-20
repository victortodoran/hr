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

const props = defineProps<{
    progress: Progress;
    performanceEvaluationId: number;
    goalId: number;
    label: string;
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

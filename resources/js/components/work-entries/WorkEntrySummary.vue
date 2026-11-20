<script setup lang="ts">
import { computed } from 'vue';
import { describePeriod, formatHours } from '@/lib/work-entries';
import type { WorkEntryAssignmentTotal, Period } from '@/types';

const props = withDefaults(
    defineProps<{
        total: number;
        declinedHours: number;
        period?: Period;
        caption?: string;
        assignmentTotals?: WorkEntryAssignmentTotal[];
        weekNumber?: number;
    }>(),
    { period: undefined, caption: undefined, assignmentTotals: () => [] },
);

const covers = computed((): string => {
    if (props.caption !== undefined || props.period === undefined) {
        return props.caption ?? '';
    }

    return describePeriod(props.period, props.weekNumber);
});
</script>

<template>
    <div
        class="flex flex-wrap items-baseline gap-x-6 gap-y-1 text-sm"
        data-test="work-entry-summary"
    >
        <p>
            <span class="text-2xl font-semibold tabular-nums">{{
                formatHours(total)
            }}</span>
            <span class="ml-1.5 text-muted-foreground">{{ covers }}</span>
        </p>
        <p
            v-if="declinedHours > 0"
            class="text-status-declined-foreground"
            data-test="declined-hours"
        >
            {{ formatHours(declinedHours) }} declined, not counted
        </p>
        <ul
            v-if="assignmentTotals.length > 0"
            class="flex flex-wrap gap-x-5 gap-y-1 text-muted-foreground"
            aria-label="By Project"
        >
            <li
                v-for="assignmentTotal in assignmentTotals"
                :key="assignmentTotal.assignment_id"
            >
                {{ assignmentTotal.project }}
                <span class="font-medium text-foreground tabular-nums">{{
                    formatHours(assignmentTotal.hours)
                }}</span>
            </li>
        </ul>
    </div>
</template>

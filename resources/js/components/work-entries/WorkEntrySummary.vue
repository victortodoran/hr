<script setup lang="ts">
import { computed } from 'vue';
import { formatMonth } from '@/lib/dates';
import type { WorkEntryAssignmentTotal, WorkEntryPeriod } from '@/types';

/**
 * The Period's total hours in large type, saying what it covers, with the
 * hours on each Project beside it, most hours first.
 */
const props = defineProps<{
    period: WorkEntryPeriod;
    total: number;
    assignmentTotals: WorkEntryAssignmentTotal[];
    /** The ISO week number, for a Period in Week mode. */
    weekNumber?: number;
}>();

/**
 * Hours as people read them, such as "7.5 h".
 */
function formatHours(hours: number): string {
    return `${hours.toLocaleString(undefined, { maximumFractionDigits: 2 })} h`;
}

function formatShort(date: string): string {
    return new Date(`${date}T00:00:00`).toLocaleDateString(undefined, {
        day: 'numeric',
        month: 'short',
    });
}

/**
 * What the total covers, such as "in November 2026", "in Week 46" or
 * "from 1 Oct to 14 Nov".
 */
const covers = computed((): string => {
    const { mode, from, to } = props.period;

    if (mode === 'month') {
        return `in ${formatMonth(from.slice(0, 7))}`;
    }

    if (mode === 'week') {
        return `in Week ${props.weekNumber}`;
    }

    return `from ${formatShort(from)} to ${formatShort(to)}`;
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

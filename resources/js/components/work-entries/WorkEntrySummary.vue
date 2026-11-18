<script setup lang="ts">
import { computed } from 'vue';
import { formatMonth } from '@/lib/dates';
import { formatHours } from '@/lib/work-entries';
import type { WorkEntryAssignmentTotal, Period } from '@/types';

/**
 * The Period's total hours in large type, saying what it covers, with the
 * hours on each Project beside it, most hours first, and the hours of any
 * Declined Work Entries, which count nowhere.
 */
const props = withDefaults(
    defineProps<{
        total: number;
        declinedHours: number;
        /** The Period the total covers, worded by `covers`. */
        period?: Period;
        /** What the total covers, in place of the Period's wording, such as "in 3 matching Work Entries". */
        caption?: string;
        assignmentTotals?: WorkEntryAssignmentTotal[];
        /** The ISO week number, for a Period in Week mode. */
        weekNumber?: number;
    }>(),
    { period: undefined, caption: undefined, assignmentTotals: () => [] },
);

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
    if (props.caption !== undefined || props.period === undefined) {
        return props.caption ?? '';
    }

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

<script setup lang="ts">
import { Clock } from '@lucide/vue';
import { computed } from 'vue';
import EmptyState from '@/components/EmptyState.vue';
import HomeWidget from '@/components/home/HomeWidget.vue';
import HoursBarChart from '@/components/HoursBarChart.vue';
import { formatMonth, formatShortMonth } from '@/lib/dates';
import { formatHours } from '@/lib/work-entries';
import { index as workEntries } from '@/routes/work-entries';
import type { HoursThisYear } from '@/types';

/**
 * The signed-in Employee's hours this year: the year's total by the title
 * and a HoursBarChart of a bar per month, this month (the last) in
 * `primary`, each opening My Work Entries on its month. With no hours yet
 * this year, it says so instead.
 */
const props = defineProps<{
    hoursThisYear: HoursThisYear;
}>();

const bars = computed(() =>
    props.hoursThisYear.months.map((month, position, months) => ({
        label: formatMonth(month.month),
        axisLabel: formatShortMonth(month.month).charAt(0),
        hours: month.hours,
        pendingHours: month.pendingHours,
        current: position === months.length - 1,
        href: workEntries({ query: { month: month.month } }),
    })),
);
</script>

<template>
    <HomeWidget title="Hours this year">
        <template #meta>
            <span
                class="text-meta text-muted-foreground tabular-nums"
                data-test="hours-this-year"
                >{{ formatHours(hoursThisYear.hours) }}</span
            >
        </template>
        <EmptyState
            v-if="hoursThisYear.hours === 0"
            :icon="Clock"
            message="You haven't recorded any hours this year."
        />
        <HoursBarChart
            v-else
            :bars="bars"
            :caption="`Your hours each month of ${hoursThisYear.year}`"
            note="Declined not counted"
        />
    </HomeWidget>
</template>

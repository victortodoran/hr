<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { formatMonth } from '@/lib/dates';
import { formatHours } from '@/lib/work-entries';
import { index as workEntries } from '@/routes/work-entries';
import type { HoursThisYear, MonthOfHours } from '@/types';

/**
 * The signed-in Employee's hours this year: the year's total and its Pending
 * part, and a bar per month sized by its hours, the Pending part shown in
 * the waiting colour on top. Each bar shows its hours, and opens My Work
 * Entries on its month.
 */
const props = defineProps<{
    hoursThisYear: HoursThisYear;
}>();

/** The busiest month's hours, which a full-height bar stands for. */
const most = computed(() =>
    Math.max(...props.hoursThisYear.months.map((month) => month.hours), 0),
);

/** A share of the busiest month, as a CSS height. */
function heightOf(hours: number): string {
    return most.value === 0 ? '0%' : `${(hours / most.value) * 100}%`;
}

/** A month's short name, such as "Nov". */
function shortName(month: string): string {
    return new Date(`${month}-01T00:00:00`).toLocaleDateString(undefined, {
        month: 'short',
    });
}

/** What a month's bar says, such as "November 2026: 96 h, 36 h Pending". */
function describe(month: MonthOfHours): string {
    return [
        `${formatMonth(month.month)}: ${formatHours(month.hours)}`,
        month.pending > 0 && `${formatHours(month.pending)} Pending`,
    ]
        .filter(Boolean)
        .join(', ');
}
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle>Hours this year</CardTitle>
            <CardDescription
                >Your hours each month of {{ hoursThisYear.year }}, leaving out
                Declined ones</CardDescription
            >
        </CardHeader>
        <CardContent class="space-y-4 text-sm">
            <p class="flex flex-wrap items-baseline gap-x-3 gap-y-1">
                <span
                    class="text-2xl font-semibold tabular-nums"
                    data-test="hours-this-year"
                    >{{ formatHours(hoursThisYear.hours) }}</span
                >
                <span
                    v-if="hoursThisYear.pending > 0"
                    class="flex items-center gap-1.5 text-muted-foreground"
                >
                    <span
                        class="size-2.5 rounded-sm bg-status-pending-foreground"
                        aria-hidden="true"
                    />
                    {{ formatHours(hoursThisYear.pending) }} Pending
                </span>
            </p>

            <ol class="flex h-40 items-stretch gap-1 sm:gap-2">
                <li
                    v-for="month in hoursThisYear.months"
                    :key="month.month"
                    class="min-w-0 flex-1"
                >
                    <Link
                        :href="workEntries({ query: { month: month.month } })"
                        :aria-label="describe(month)"
                        :title="describe(month)"
                        class="group flex h-full flex-col items-center gap-1 rounded-sm focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                        data-test="month-of-hours"
                    >
                        <span
                            class="text-[10px] text-muted-foreground tabular-nums sm:text-xs"
                            aria-hidden="true"
                            >{{ month.hours }}</span
                        >
                        <span class="flex w-full flex-1 flex-col justify-end">
                            <span
                                class="flex w-full flex-col overflow-hidden rounded-t-sm bg-foreground/70 transition-colors group-hover:bg-foreground"
                                :style="{ height: heightOf(month.hours) }"
                            >
                                <span
                                    class="w-full bg-status-pending-foreground"
                                    :style="{
                                        height:
                                            month.hours === 0
                                                ? '0%'
                                                : `${(month.pending / month.hours) * 100}%`,
                                    }"
                                />
                            </span>
                        </span>
                        <span
                            class="text-[10px] text-muted-foreground sm:text-xs"
                            aria-hidden="true"
                            >{{ shortName(month.month) }}</span
                        >
                    </Link>
                </li>
            </ol>
        </CardContent>
    </Card>
</template>

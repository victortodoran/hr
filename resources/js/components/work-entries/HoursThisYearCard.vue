<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Clock } from '@lucide/vue';
import { computed } from 'vue';
import EmptyState from '@/components/EmptyState.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { formatMonth, formatShortMonth } from '@/lib/dates';
import { formatHours } from '@/lib/work-entries';
import { index as workEntries } from '@/routes/work-entries';
import type { HoursThisYear, MonthOfHours } from '@/types';

/**
 * The signed-in Employee's hours this year: the year's total and its Pending
 * part, and a bar per month sized by its hours, the Pending part shown in
 * the waiting colour on top. Each bar shows its hours, and opens My Work
 * Entries on its month. With no hours yet this year, it says so instead.
 */
const props = defineProps<{
    hoursThisYear: HoursThisYear;
}>();

/** The busiest month's hours, which a full-height bar stands for. */
const most = computed(() =>
    Math.max(...props.hoursThisYear.months.map((month) => month.hours), 0),
);

/** A part's share of a whole, as a CSS height; none of nothing. */
function shareOf(part: number, whole: number): string {
    return whole === 0 ? '0%' : `${(part / whole) * 100}%`;
}

/** Hours as a bar's short label, such as "7.3". */
function shortHours(hours: number): string {
    return hours.toLocaleString(undefined, { maximumFractionDigits: 1 });
}

/** What a month's bar says, such as "November 2026: 96 h, 36 h Pending". */
function describe(month: MonthOfHours): string {
    return [
        `${formatMonth(month.month)}: ${formatHours(month.hours)}`,
        month.pendingHours > 0 && `${formatHours(month.pendingHours)} Pending`,
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
        <CardContent v-if="hoursThisYear.hours === 0">
            <EmptyState
                :icon="Clock"
                message="You haven't recorded any hours this year."
            >
                <template #action>
                    <Button variant="outline" as-child>
                        <Link
                            :href="
                                workEntries({
                                    query: {
                                        month: hoursThisYear.months.at(-1)
                                            ?.month,
                                    },
                                })
                            "
                            >Open My Work Entries</Link
                        >
                    </Button>
                </template>
            </EmptyState>
        </CardContent>
        <CardContent v-else class="space-y-4 text-sm">
            <p class="flex flex-wrap items-baseline gap-x-3 gap-y-1">
                <span
                    class="text-2xl font-semibold tabular-nums"
                    data-test="hours-this-year"
                    >{{ formatHours(hoursThisYear.hours) }}</span
                >
                <span
                    v-if="hoursThisYear.pendingHours > 0"
                    class="flex items-center gap-1.5 text-muted-foreground"
                >
                    <span
                        class="size-2.5 rounded-sm bg-status-pending-foreground"
                        aria-hidden="true"
                    />
                    {{ formatHours(hoursThisYear.pendingHours) }} Pending
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
                            >{{ shortHours(month.hours) }}</span
                        >
                        <span class="flex w-full flex-1 flex-col justify-end">
                            <span
                                class="flex w-full flex-col overflow-hidden rounded-t-sm bg-foreground/70 transition-colors group-hover:bg-foreground"
                                :style="{
                                    height: shareOf(month.hours, most),
                                }"
                            >
                                <span
                                    class="w-full bg-status-pending-foreground"
                                    :style="{
                                        height: shareOf(
                                            month.pendingHours,
                                            month.hours,
                                        ),
                                    }"
                                />
                            </span>
                        </span>
                        <span
                            class="text-[10px] text-muted-foreground sm:text-xs"
                            aria-hidden="true"
                            >{{ formatShortMonth(month.month) }}</span
                        >
                    </Link>
                </li>
            </ol>
        </CardContent>
    </Card>
</template>

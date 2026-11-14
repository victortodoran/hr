<script setup lang="ts">
import { computed } from 'vue';
import StatusBadge from '@/components/StatusBadge.vue';
import type {
    WorkEntryCalendarDay,
    WorkEntryCalendarWeek,
    WorkEntryState,
} from '@/types';

/**
 * A read-only month of Work Entries, one row per ISO week, Monday to
 * Sunday, or Monday to Friday with weekends hidden. Each row starts with
 * its week number and hours; each day shows its hours, one line per
 * Project and state as a status badge, and its Time Off or
 * nothing logged. On a phone each day shows only its hours and a dot per
 * state and for Time Off. Days of other months are left blank.
 */
const props = defineProps<{
    weeks: WorkEntryCalendarWeek[];
    today: string;
    showWeekends: boolean;
}>();

/**
 * Hours as people read them, such as "7.5 h".
 */
function formatHours(hours: number): string {
    return `${hours.toLocaleString(undefined, { maximumFractionDigits: 2 })} h`;
}

const weekdays = computed(() =>
    ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'].slice(
        0,
        props.showWeekends ? 7 : 5,
    ),
);

/** A row's days, without Saturday and Sunday when weekends are hidden. */
function shownDays(
    week: WorkEntryCalendarWeek,
): (WorkEntryCalendarDay | null)[] {
    return props.showWeekends ? week.days : week.days.slice(0, 5);
}

/** The states a day has hours in, Pending first, for its dots on a phone. */
function statesOf(day: WorkEntryCalendarDay): WorkEntryState[] {
    return (['pending', 'approved', 'locked'] as const).filter((state) =>
        day.projects.some((onProject) => onProject.state === state),
    );
}

function timeOffLabel(day: WorkEntryCalendarDay): string {
    return day.time_off === 'paid' ? 'Paid Time Off' : 'Unpaid Time Off';
}

const columns = computed(() =>
    props.showWeekends
        ? 'grid-cols-[3.5rem_repeat(7,minmax(0,1fr))]'
        : 'grid-cols-[3.5rem_repeat(5,minmax(0,1fr))]',
);
</script>

<template>
    <div class="overflow-hidden rounded-lg border text-sm">
        <div
            :class="[
                'grid border-b bg-muted/50 text-xs text-muted-foreground',
                columns,
            ]"
        >
            <span class="px-2 py-1.5">Week</span>
            <span
                v-for="weekday in weekdays"
                :key="weekday"
                class="border-l px-2 py-1.5"
                >{{ weekday }}</span
            >
        </div>
        <div
            v-for="week in weeks"
            :key="week.number"
            :class="['grid border-b last:border-b-0', columns]"
        >
            <div class="flex flex-col px-2 py-2">
                <span class="font-medium">W{{ week.number }}</span>
                <span class="text-xs text-muted-foreground tabular-nums">{{
                    formatHours(week.hours)
                }}</span>
            </div>
            <div
                v-for="(day, index) in shownDays(week)"
                :key="day?.date ?? `blank-${index}`"
                :class="[
                    'min-h-24 border-l px-2 py-2',
                    day === null && 'bg-muted/30',
                ]"
                :data-date="day?.date"
            >
                <template v-if="day">
                    <div
                        class="flex flex-col items-start gap-0.5 sm:flex-row sm:items-baseline sm:justify-between sm:gap-1"
                    >
                        <span
                            :class="[
                                'font-medium',
                                day.date === today &&
                                    'rounded-full bg-foreground px-1.5 text-background',
                            ]"
                            >{{ Number(day.date.slice(8)) }}</span
                        >
                        <span
                            v-if="day.hours > 0"
                            class="text-xs font-medium whitespace-nowrap tabular-nums"
                            >{{ formatHours(day.hours) }}</span
                        >
                    </div>
                    <div
                        v-if="day.projects.length > 0 || day.time_off"
                        class="mt-1 flex flex-wrap gap-1 sm:hidden"
                    >
                        <StatusBadge
                            v-for="state in statesOf(day)"
                            :key="state"
                            :state="state"
                            class="size-2.5 p-0 ring-1 ring-current"
                            ><span class="sr-only">{{
                                state
                            }}</span></StatusBadge
                        >
                        <span
                            v-if="day.time_off"
                            class="size-2.5 rounded-full bg-muted ring-1 ring-muted-foreground"
                            :title="timeOffLabel(day)"
                            ><span class="sr-only">{{
                                timeOffLabel(day)
                            }}</span></span
                        >
                    </div>
                    <ul
                        v-if="day.projects.length > 0"
                        class="mt-1 hidden space-y-0.5 sm:block"
                    >
                        <li
                            v-for="onProject in day.projects"
                            :key="`${onProject.project}-${onProject.state}`"
                        >
                            <StatusBadge
                                :state="onProject.state"
                                class="flex w-full justify-between gap-1 rounded px-1.5 font-normal"
                                :data-state="onProject.state"
                                ><span class="min-w-0 truncate">{{
                                    onProject.project
                                }}</span
                                ><span class="shrink-0 tabular-nums">{{
                                    formatHours(onProject.hours)
                                }}</span></StatusBadge
                            >
                        </li>
                    </ul>
                    <p
                        v-if="day.time_off"
                        class="mt-1 hidden rounded-full bg-muted px-2 py-0.5 text-xs font-medium sm:block"
                    >
                        {{ timeOffLabel(day) }}
                    </p>
                    <p
                        v-else-if="day.nothing_logged"
                        class="mt-1 hidden text-xs text-muted-foreground italic sm:block"
                    >
                        Nothing logged
                    </p>
                </template>
            </div>
        </div>
    </div>
</template>

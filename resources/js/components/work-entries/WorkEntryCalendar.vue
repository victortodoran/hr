<script setup lang="ts">
import { computed } from 'vue';
import StatusDot from '@/components/StatusDot.vue';
import { statusLabels } from '@/lib/status';
import { timeOffKindLabel } from '@/lib/time-off';
import type {
    WorkEntryCalendarDay,
    WorkEntryCalendarWeek,
    WorkEntryState,
} from '@/types';

/**
 * A read-only month of Work Entries, one row per ISO week, Monday to
 * Sunday, or Monday to Friday with weekends hidden. Each row starts with
 * its week number and hours; each day shows its hours, a line per Work
 * Entry (a dot in its state's colour, what was done and its hours, at most
 * three and then how many more), and its Time Off or nothing logged. On a
 * phone each day shows only its hours and a dot per state and for Time
 * Off. Days of other months are left blank; a legend names the dots.
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

/** The Work Entry states, in the order the dots and legend show them. */
const states = ['pending', 'approved', 'locked'] as const;

/**
 * The Work Entries a day leaves out after its first three, as a tooltip:
 * one per line, such as "Relaunch · Team meeting · 1 h".
 */
function moreTitle(day: WorkEntryCalendarDay): string {
    return day.entries
        .slice(shownEntries)
        .map(
            (entry) =>
                `${entry.project} · ${entry.description} · ${formatHours(entry.hours)}`,
        )
        .join('\n');
}

/** How many Work Entry lines a day shows before "+2 more". */
const shownEntries = 3;

/** The states a day has hours in, Pending first, for its dots on a phone. */
function statesOf(day: WorkEntryCalendarDay): WorkEntryState[] {
    return states.filter((state) =>
        day.entries.some((entry) => entry.state === state),
    );
}

function timeOffLabel(day: WorkEntryCalendarDay): string {
    return day.time_off ? timeOffKindLabel(day.time_off) : '';
}

const columns = computed(() =>
    props.showWeekends
        ? 'grid-cols-[3.5rem_repeat(7,minmax(0,1fr))]'
        : 'grid-cols-[3.5rem_repeat(5,minmax(0,1fr))]',
);
</script>

<template>
    <div class="flex flex-col gap-3">
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
                        index >= 5 && day !== null && 'bg-muted/20',
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
                                    day.date > today && 'text-muted-foreground',
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
                            v-if="day.entries.length > 0 || day.time_off"
                            class="mt-1 flex flex-wrap gap-1 sm:hidden"
                        >
                            <StatusDot
                                v-for="state in statesOf(day)"
                                :key="state"
                                :state="state"
                                class="size-2.5"
                            />
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
                            v-if="day.entries.length > 0"
                            class="mt-1 hidden space-y-0.5 sm:block"
                        >
                            <li
                                v-for="entry in day.entries.slice(
                                    0,
                                    shownEntries,
                                )"
                                :key="entry.id"
                                class="flex items-center gap-1.5 text-xs"
                                :data-state="entry.state"
                            >
                                <StatusDot :state="entry.state" />
                                <span
                                    class="min-w-0 flex-1 truncate"
                                    :title="`${entry.project} · ${entry.description}`"
                                    >{{ entry.description }}</span
                                >
                                <span
                                    class="shrink-0 text-muted-foreground tabular-nums"
                                    >{{ formatHours(entry.hours) }}</span
                                >
                            </li>
                            <li
                                v-if="day.entries.length > shownEntries"
                                class="text-xs text-muted-foreground"
                                :title="moreTitle(day)"
                            >
                                +{{ day.entries.length - shownEntries }} more
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
        <ul
            class="flex flex-wrap gap-4 text-xs text-muted-foreground"
            aria-label="Legend"
        >
            <li
                v-for="state in states"
                :key="state"
                class="flex items-center gap-1.5"
            >
                <StatusDot :state="state" labelled />
                <span>{{ statusLabels[state] }}</span>
            </li>
            <li class="flex items-center gap-1.5">
                <span
                    class="inline-block size-2 rounded-full bg-muted ring-1 ring-muted-foreground"
                    aria-hidden="true"
                />
                <span>Time Off</span>
            </li>
        </ul>
    </div>
</template>

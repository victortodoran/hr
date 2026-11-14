<script setup lang="ts">
import { useMediaQuery } from '@vueuse/core';
import { computed, ref, watch } from 'vue';
import StatusDot from '@/components/StatusDot.vue';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import {
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import WorkEntryDayPanel from '@/components/work-entries/WorkEntryDayPanel.vue';
import { formatLongDay } from '@/lib/dates';
import { statusLabels } from '@/lib/status';
import { timeOffKindLabel } from '@/lib/time-off';
import type {
    Choice,
    WorkEntryAssignment,
    WorkEntryCalendarWeek,
    WorkEntryDay,
    WorkEntryPeriod,
    WorkEntryState,
} from '@/types';

/**
 * A month of Work Entries, one row per ISO week, Monday to
 * Sunday, or Monday to Friday with weekends hidden. Each row starts with
 * its week number and hours; each day shows its hours, a line per Work
 * Entry (a dot in its state's colour, what was done and its hours, at most
 * three and then how many more), and its Time Off or nothing logged. On a
 * phone each day shows only its hours and a dot per state and for Time
 * Off. Days of other months are left blank; a legend names the dots.
 * Clicking a day opens it up (WorkEntryDayPanel): in a popover pointing at
 * it, or on a phone in a sheet from the bottom.
 */
const props = defineProps<{
    weeks: WorkEntryCalendarWeek[];
    today: string;
    showWeekends: boolean;
    employee: Choice;
    period: WorkEntryPeriod;
    assignments: WorkEntryAssignment[];
    canManage: boolean;
}>();

const emit = defineEmits<{
    /** Whether an add form holds typing that isn't saved yet, by row key. */
    dirty: [rowKey: string, isDirty: boolean];
}>();

/** Whether the screen is wide enough for a popover rather than a sheet. */
const isWide = useMediaQuery('(min-width: 640px)');

/** The Period as changes made on the Calendar send it, so they come back to the Calendar. */
const calendarPeriod = computed((): WorkEntryPeriod => ({
    ...props.period,
    query: { ...props.period.query, tab: 'calendar' },
}));

/** The day opened up, if any. */
const openDate = ref<string | null>(null);

/** The Assignment of the Work Entry last added on this page. */
const lastAssignmentId = ref<number | null>(null);

const monthDays = computed(() =>
    props.weeks.flatMap((week) =>
        week.days.filter((day): day is WorkEntryDay => day !== null),
    ),
);

const openDay = computed(
    () => monthDays.value.find((day) => day.date === openDate.value) ?? null,
);

/** Another month closes the day opened up. */
watch(
    () => JSON.stringify(props.period.query),
    () => {
        openDate.value = null;
    },
);

function setOpen(date: string, isOpen: boolean): void {
    if (isOpen) {
        openDate.value = date;
    } else if (openDate.value === date) {
        openDate.value = null;
    }
}

/** Whether the viewer can add hours to a day: one up to today. */
function takesEntries(day: WorkEntryDay): boolean {
    return props.canManage && day.date <= props.today;
}

function isWeekend(date: string): boolean {
    const weekday = new Date(`${date}T00:00:00`).getDay();

    return weekday === 0 || weekday === 6;
}

/** Whether a day shows the "+ Add" hint: an empty weekday hours can be added to. */
function invitesEntries(day: WorkEntryDay): boolean {
    return (
        takesEntries(day) &&
        !isWeekend(day.date) &&
        day.time_off === null &&
        day.entries.length === 0
    );
}

/** The next weekday of the month after a day that shows the "+ Add" hint. */
function nextEmptyDay(date: string): string | null {
    return (
        monthDays.value.find((day) => day.date > date && invitesEntries(day))
            ?.date ?? null
    );
}

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
function shownDays(week: WorkEntryCalendarWeek): (WorkEntryDay | null)[] {
    return props.showWeekends ? week.days : week.days.slice(0, 5);
}

/** The Work Entry states, in the order the dots and legend show them. */
const states = ['pending', 'approved', 'locked'] as const;

/**
 * The Work Entries a day leaves out after its first three, as a tooltip:
 * one per line, such as "Relaunch · Team meeting · 1 h".
 */
function moreTitle(day: WorkEntryDay): string {
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
function statesOf(day: WorkEntryDay): WorkEntryState[] {
    return states.filter((state) =>
        day.entries.some((entry) => entry.state === state),
    );
}

function timeOffLabel(day: WorkEntryDay): string {
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
                        'relative min-h-24 border-l',
                        day === null && 'bg-muted/30',
                        index >= 5 && day !== null && 'bg-muted/20',
                        day !== null &&
                            openDate === day.date &&
                            'ring-2 ring-primary ring-inset',
                    ]"
                    :data-date="day?.date"
                >
                    <template v-if="day">
                        <Popover
                            v-if="isWide"
                            :open="openDate === day.date"
                            @update:open="setOpen(day.date, $event)"
                        >
                            <PopoverTrigger as-child>
                                <button
                                    type="button"
                                    class="absolute inset-0 cursor-pointer outline-none hover:bg-muted/40 focus-visible:bg-muted/40"
                                    :aria-label="`${takesEntries(day) ? 'Add or view entries' : 'View'}, ${formatLongDay(day.date)}`"
                                />
                            </PopoverTrigger>
                            <PopoverContent
                                :align="index >= 4 ? 'end' : 'start'"
                                class="w-[23rem]"
                            >
                                <WorkEntryDayPanel
                                    :day="day"
                                    :employee="employee"
                                    :period="calendarPeriod"
                                    :today="today"
                                    :assignments="assignments"
                                    :takes-entries="takesEntries(day)"
                                    :next-empty-day="nextEmptyDay(day.date)"
                                    :last-assignment-id="lastAssignmentId"
                                    @added="lastAssignmentId = $event"
                                    @open="openDate = $event"
                                    @close="openDate = null"
                                    @dirty="
                                        emit(
                                            'dirty',
                                            `calendar-${day.date}`,
                                            $event,
                                        )
                                    "
                                />
                            </PopoverContent>
                        </Popover>
                        <button
                            v-else
                            type="button"
                            class="absolute inset-0 cursor-pointer outline-none focus-visible:bg-muted/40"
                            :aria-label="`${takesEntries(day) ? 'Add or view entries' : 'View'}, ${formatLongDay(day.date)}`"
                            @click="openDate = day.date"
                        />
                        <div
                            class="pointer-events-none relative flex h-full flex-col px-2 py-2"
                        >
                            <div
                                class="flex flex-col items-start gap-0.5 sm:flex-row sm:items-baseline sm:justify-between sm:gap-1"
                            >
                                <span
                                    :class="[
                                        'font-medium',
                                        day.date === today &&
                                            'rounded-full bg-foreground px-1.5 text-background',
                                        day.date > today &&
                                            'text-muted-foreground',
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
                                    +{{ day.entries.length - shownEntries }}
                                    more
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
                            <span
                                v-if="invitesEntries(day)"
                                class="mt-auto hidden h-6 items-center justify-center rounded-md border border-dashed text-xs text-muted-foreground sm:flex"
                                >+ Add</span
                            >
                        </div>
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
            <li>
                {{
                    canManage
                        ? 'Click a day to add or see its entries'
                        : 'Click a day to see its entries'
                }}
            </li>
        </ul>

        <Sheet
            :open="!isWide && openDay !== null"
            @update:open="(isOpen) => !isOpen && (openDate = null)"
        >
            <SheetContent
                side="bottom"
                class="max-h-[85vh] overflow-y-auto p-4"
            >
                <SheetHeader class="sr-only">
                    <SheetTitle>{{
                        openDay ? formatLongDay(openDay.date) : ''
                    }}</SheetTitle>
                </SheetHeader>
                <WorkEntryDayPanel
                    v-if="openDay"
                    :day="openDay"
                    :employee="employee"
                    :period="calendarPeriod"
                    :today="today"
                    :assignments="assignments"
                    :takes-entries="takesEntries(openDay)"
                    :next-empty-day="nextEmptyDay(openDay.date)"
                    :last-assignment-id="lastAssignmentId"
                    @added="lastAssignmentId = $event"
                    @open="openDate = $event"
                    @close="openDate = null"
                    @dirty="emit('dirty', `calendar-${openDay.date}`, $event)"
                />
            </SheetContent>
        </Sheet>
    </div>
</template>

<script setup lang="ts">
import { Sun } from '@lucide/vue';
import { useMediaQuery } from '@vueuse/core';
import { computed, nextTick, ref, watch } from 'vue';
import StatusIcon from '@/components/StatusIcon.vue';
import {
    Popover,
    PopoverAnchor,
    PopoverContent,
} from '@/components/ui/popover';
import {
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import UnsavedWorkEntryDialog from '@/components/work-entries/UnsavedWorkEntryDialog.vue';
import WorkDayPanel from '@/components/work-entries/WorkDayPanel.vue';
import WorkWeekAgenda from '@/components/work-entries/WorkWeekAgenda.vue';
import { formatLongDay, isWeekend } from '@/lib/dates';
import { statusLabels } from '@/lib/statusMeta';
import { timeOffKindLabel } from '@/lib/time-off';
import {
    formatHours,
    takesEntries as takesEntriesOn,
} from '@/lib/work-entries';
import type {
    Choice,
    Period,
    WorkEntryAssignment,
    WorkEntryCalendarWeek,
    WorkEntryDay,
} from '@/types';

const props = defineProps<{
    weeks: WorkEntryCalendarWeek[];
    today: string;
    showWeekends: boolean;
    employee: Choice;
    period: Period;
    assignments: WorkEntryAssignment[];
    canManage: boolean;
}>();

const emit = defineEmits<{
    dirty: [rowKey: string, isDirty: boolean];
    month: [step: -1 | 1];
}>();

const isWide = useMediaQuery('(min-width: 640px)');

/** The Period as changes made on the Calendar send it, so they come back to the Calendar. */
const calendarPeriod = computed((): Period => ({
    ...props.period,
    query: { ...props.period.query, tab: 'calendar' },
}));

const openDate = ref<string | null>(null);

const lastAssignmentId = ref<number | null>(null);

const monthDays = computed(() =>
    props.weeks.flatMap((week) =>
        week.days.filter((day): day is WorkEntryDay => day !== null),
    ),
);

const shownMonthDays = computed(() =>
    monthDays.value.filter((day) => props.showWeekends || !isWeekend(day.date)),
);

const openDay = computed(
    () => monthDays.value.find((day) => day.date === openDate.value) ?? null,
);

const selectedDate = ref<string>('');

function selectDefault(): void {
    const shown = shownMonthDays.value;

    selectedDate.value =
        shown.find((day) => day.date === props.today)?.date ??
        shown[0]?.date ??
        '';
}

selectDefault();

watch(
    () => JSON.stringify(props.period.query),
    () => {
        openDate.value = null;
        selectDefault();
    },
);

watch(
    () => props.showWeekends,
    () => {
        if (
            !shownMonthDays.value.some((day) => day.date === selectedDate.value)
        ) {
            selectDefault();
        }
    },
);

const dirtyDate = ref<string | null>(null);

const awaitingLeave = ref<{ date: string | null } | null>(null);

function markDirty(date: string, isDirty: boolean): void {
    if (isDirty) {
        dirtyDate.value = date;
    } else if (dirtyDate.value === date) {
        dirtyDate.value = null;
    }

    emit('dirty', `calendar-${date}`, isDirty);
}

function show(date: string | null): void {
    if (date === openDate.value) {
        return;
    }

    if (dirtyDate.value !== null) {
        awaitingLeave.value = { date };

        return;
    }

    openDate.value = date;

    if (date !== null) {
        selectedDate.value = date;
    }
}

function holdUnsaved(event: Event): void {
    if (dirtyDate.value === null) {
        return;
    }

    event.preventDefault();
    awaitingLeave.value ??= { date: null };
}

function leave(): void {
    const date = awaitingLeave.value?.date ?? null;

    awaitingLeave.value = null;
    dirtyDate.value = null;
    openDate.value = date;

    if (date !== null) {
        selectedDate.value = date;
    }
}

async function returnFocus(event: Event): Promise<void> {
    event.preventDefault();
    await nextTick();

    if (openDate.value === null) {
        dayButton(selectedDate.value)?.focus();

        return;
    }

    document
        .querySelector<HTMLElement>(`#work-entry-hours-${openDate.value}`)
        ?.focus();
}

function takesEntries(day: WorkEntryDay): boolean {
    return takesEntriesOn(day, props.canManage, props.today);
}

function shownDayFrom(date: string, step: -1 | 1): string | null {
    const shown = shownMonthDays.value;
    const index = shown.findIndex((day) => day.date === date);

    return shown[index + step]?.date ?? null;
}

function dayButton(date: string): HTMLButtonElement | null {
    return document.querySelector<HTMLButtonElement>(
        `[data-calendar-day="${date}"]`,
    );
}

function addDays(date: string, days: number): string {
    const moved = new Date(`${date}T00:00:00Z`);
    moved.setUTCDate(moved.getUTCDate() + days);

    return moved.toISOString().slice(0, 10);
}

function moveFocus(event: KeyboardEvent): void {
    const from = selectedDate.value;
    let to: string | null = null;

    if (event.key === 'ArrowLeft' || event.key === 'ArrowRight') {
        to = shownDayFrom(from, event.key === 'ArrowLeft' ? -1 : 1);
    } else if (event.key === 'ArrowUp' || event.key === 'ArrowDown') {
        const date = addDays(from, event.key === 'ArrowUp' ? -7 : 7);
        to = shownMonthDays.value.some((day) => day.date === date)
            ? date
            : null;
    } else {
        return;
    }

    event.preventDefault();

    if (to !== null) {
        selectedDate.value = to;
        dayButton(to)?.focus();
    }
}

const weekdays = computed(() =>
    ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'].slice(
        0,
        props.showWeekends ? 7 : 5,
    ),
);

function shownDays(week: WorkEntryCalendarWeek): (WorkEntryDay | null)[] {
    return props.showWeekends ? week.days : week.days.slice(0, 5);
}

const states = ['pending', 'approved', 'declined'] as const;

const shownEntries = 3;

function dayLabel(day: WorkEntryDay): string {
    const parts = [formatLongDay(day.date)];

    if (day.date === props.today) {
        parts.push('today');
    }

    parts.push(
        day.entries.length > 0
            ? `${formatHours(day.hours)} logged`
            : day.time_off
              ? timeOffKindLabel(day.time_off)
              : 'nothing logged',
    );

    return parts.join(', ');
}

const columns = computed(() =>
    props.showWeekends
        ? 'grid-cols-[4rem_repeat(7,minmax(0,1fr))]'
        : 'grid-cols-[4rem_repeat(5,minmax(0,1fr))]',
);

defineExpose({ show });
</script>

<template>
    <div class="flex flex-col gap-3">
        <template v-if="isWide">
            <div
                class="overflow-hidden rounded-lg border text-body"
                role="grid"
                aria-label="Work Entries by day"
                @keydown="moveFocus"
            >
                <div
                    role="row"
                    :class="[
                        'grid border-b bg-surface text-caption text-muted-foreground',
                        columns,
                    ]"
                >
                    <span role="columnheader" class="px-3 py-2">Week</span>
                    <span
                        v-for="weekday in weekdays"
                        :key="weekday"
                        role="columnheader"
                        class="border-l px-3 py-2"
                        >{{ weekday }}</span
                    >
                </div>
                <div
                    v-for="week in weeks"
                    :key="week.number"
                    role="row"
                    :class="['grid border-b last:border-b-0', columns]"
                >
                    <div
                        role="rowheader"
                        class="flex flex-col gap-0.5 bg-surface px-3 py-2"
                    >
                        <span class="text-meta font-semibold"
                            >W{{ week.number }}</span
                        >
                        <span
                            class="text-caption text-muted-foreground tabular-nums"
                            >{{ formatHours(week.hours) }}</span
                        >
                    </div>
                    <div
                        v-for="(day, index) in shownDays(week)"
                        :key="day?.date ?? `blank-${index}`"
                        role="gridcell"
                        :class="[
                            'relative min-h-28 border-l',
                            day === null && 'bg-surface',
                            index >= 5 && day !== null && 'bg-surface/60',
                            day !== null &&
                                selectedDate === day.date &&
                                'bg-primary-soft ring-2 ring-primary ring-inset',
                        ]"
                        :data-date="day?.date"
                        :aria-selected="
                            day !== null && selectedDate === day.date
                        "
                    >
                        <template v-if="day">
                            <Popover
                                :open="openDate === day.date"
                                @update:open="
                                    (isOpen) =>
                                        isOpen
                                            ? show(day.date)
                                            : openDate === day.date &&
                                              show(null)
                                "
                            >
                                <PopoverAnchor as-child>
                                    <button
                                        type="button"
                                        class="absolute inset-0 cursor-pointer outline-none hover:bg-surface/80 focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-inset"
                                        :data-calendar-day="day.date"
                                        :tabindex="
                                            selectedDate === day.date ? 0 : -1
                                        "
                                        :aria-label="dayLabel(day)"
                                        :aria-haspopup="'dialog'"
                                        :aria-expanded="openDate === day.date"
                                        @click="show(day.date)"
                                        @focus="selectedDate = day.date"
                                    />
                                </PopoverAnchor>
                                <PopoverContent
                                    :side="index >= 4 ? 'left' : 'right'"
                                    align="start"
                                    class="w-[25.5rem] p-4"
                                    @interact-outside="holdUnsaved"
                                    @escape-key-down="holdUnsaved"
                                    @close-auto-focus="returnFocus"
                                >
                                    <WorkDayPanel
                                        :day="day"
                                        :employee="employee"
                                        :period="calendarPeriod"
                                        :today="today"
                                        :assignments="assignments"
                                        :takes-entries="takesEntries(day)"
                                        :previous-day="
                                            shownDayFrom(day.date, -1)
                                        "
                                        :next-day="shownDayFrom(day.date, 1)"
                                        :last-assignment-id="lastAssignmentId"
                                        @added="lastAssignmentId = $event"
                                        @open="show"
                                        @close="show(null)"
                                        @dirty="markDirty"
                                    />
                                </PopoverContent>
                            </Popover>
                            <div
                                class="pointer-events-none relative flex h-full flex-col gap-1 px-2.5 py-2"
                            >
                                <div
                                    class="flex items-center justify-between gap-1"
                                >
                                    <span
                                        :class="[
                                            'grid size-6 place-items-center rounded-full text-meta font-semibold tabular-nums',
                                            day.date === today &&
                                                'bg-primary text-primary-foreground',
                                            day.date > today &&
                                                'font-medium text-subtle-foreground',
                                        ]"
                                        >{{ Number(day.date.slice(8)) }}</span
                                    >
                                    <span
                                        v-if="day.hours > 0"
                                        class="text-caption font-medium whitespace-nowrap text-muted-foreground tabular-nums"
                                        >{{ formatHours(day.hours) }}</span
                                    >
                                </div>
                                <ul
                                    v-if="day.entries.length > 0"
                                    class="flex flex-col gap-0.5"
                                >
                                    <li
                                        v-for="entry in day.entries.slice(
                                            0,
                                            shownEntries,
                                        )"
                                        :key="entry.id"
                                        class="flex items-center gap-1.5 text-caption"
                                        :data-state="entry.state"
                                    >
                                        <StatusIcon :state="entry.state" />
                                        <span
                                            :class="[
                                                'min-w-0 flex-1 truncate',
                                                entry.declined &&
                                                    'text-muted-foreground line-through',
                                            ]"
                                            >{{ entry.description }}</span
                                        >
                                    </li>
                                    <li
                                        v-if="day.entries.length > shownEntries"
                                        class="text-caption text-muted-foreground"
                                    >
                                        +{{ day.entries.length - shownEntries }}
                                        more
                                    </li>
                                </ul>
                                <p
                                    v-if="day.time_off"
                                    class="flex items-center gap-1.5 text-caption text-muted-foreground"
                                >
                                    <Sun class="size-3.5" aria-hidden="true" />
                                    {{ timeOffKindLabel(day.time_off) }}
                                </p>
                                <p
                                    v-else-if="day.nothing_logged"
                                    class="text-caption text-subtle-foreground"
                                >
                                    Nothing logged
                                </p>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </template>

        <WorkWeekAgenda
            v-else
            :weeks="weeks"
            :today="today"
            :show-weekends="showWeekends"
            :selected-date="openDate"
            @open="show"
            @month="(step) => emit('month', step)"
        />

        <ul
            class="flex flex-wrap items-center gap-x-4 gap-y-2 text-caption text-muted-foreground"
            aria-label="Legend"
        >
            <li
                v-for="state in states"
                :key="state"
                class="flex items-center gap-1.5"
            >
                <StatusIcon :state="state" labelled />
                <span>{{ statusLabels[state] }}</span>
            </li>
            <li class="flex items-center gap-1.5">
                <Sun class="size-3.5" aria-hidden="true" />
                <span>Time Off</span>
            </li>
            <li v-if="isWide" class="ml-auto flex items-center gap-1">
                Select a day, or use
                <kbd class="rounded border px-1">←</kbd>
                <kbd class="rounded border px-1">→</kbd> and
                <kbd class="rounded border px-1">↵</kbd>
            </li>
        </ul>

        <Sheet
            :open="!isWide && openDay !== null"
            @update:open="(isOpen) => !isOpen && show(null)"
        >
            <SheetContent
                side="bottom"
                class="max-h-[90vh] overflow-y-auto rounded-t-xl p-4 pb-0"
                @interact-outside="holdUnsaved"
                @escape-key-down="holdUnsaved"
            >
                <SheetHeader class="sr-only">
                    <SheetTitle>{{
                        openDay ? formatLongDay(openDay.date) : ''
                    }}</SheetTitle>
                </SheetHeader>
                <WorkDayPanel
                    v-if="openDay"
                    :key="openDay.date"
                    in-sheet
                    :day="openDay"
                    :employee="employee"
                    :period="calendarPeriod"
                    :today="today"
                    :assignments="assignments"
                    :takes-entries="takesEntries(openDay)"
                    :previous-day="shownDayFrom(openDay.date, -1)"
                    :next-day="shownDayFrom(openDay.date, 1)"
                    :last-assignment-id="lastAssignmentId"
                    @added="lastAssignmentId = $event"
                    @open="show"
                    @close="show(null)"
                    @dirty="markDirty"
                />
            </SheetContent>
        </Sheet>

        <UnsavedWorkEntryDialog
            :open="awaitingLeave !== null"
            @stay="awaitingLeave = null"
            @leave="leave"
            @close-auto-focus="returnFocus"
        />
    </div>
</template>

<script setup lang="ts">
import { ChevronLeft, ChevronRight, Sun } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import StatusIcon from '@/components/StatusIcon.vue';
import { Button } from '@/components/ui/button';
import { formatDateRange, formatLongDay, isWeekend } from '@/lib/dates';
import { timeOffKindLabel } from '@/lib/time-off';
import { formatHours } from '@/lib/work-entries';
import type { WorkEntryCalendarWeek, WorkEntryDay } from '@/types';

/**
 * The Calendar on a phone (rule P10): one week of the month at a time, a
 * strip of its days with each one's state icon, then a row per day with
 * its Work Entries and hours, today highlighted. ‹ › step a week, and on
 * from the month's first or last week to the month before or after.
 * Tapping a day opens it in the day panel's bottom sheet.
 */
const props = defineProps<{
    weeks: WorkEntryCalendarWeek[];
    today: string;
    showWeekends: boolean;
    /** The day open in the panel, if any. */
    selectedDate: string | null;
}>();

const emit = defineEmits<{
    open: [date: string];
    /** Show the month before (-1) or after (1). */
    month: [step: -1 | 1];
}>();

/** The week holding today, or else the month's first. */
function defaultWeek(): number {
    const index = props.weeks.findIndex((week) =>
        week.days.some((day) => day?.date === props.today),
    );

    return Math.max(index, 0);
}

const weekIndex = ref(defaultWeek());

/** Which end of the next month to start on, once it arrives. */
const arriving = ref<'first' | 'last' | null>(null);

watch(
    () => props.weeks,
    () => {
        weekIndex.value =
            arriving.value === 'last'
                ? props.weeks.length - 1
                : arriving.value === 'first'
                  ? 0
                  : defaultWeek();
        arriving.value = null;
    },
);

const week = computed(() => props.weeks[weekIndex.value]);

/** The week's days in the month, without weekends when hidden. */
const days = computed(() =>
    (week.value?.days ?? []).filter(
        (day): day is WorkEntryDay =>
            day !== null && (props.showWeekends || !isWeekend(day.date)),
    ),
);

/** The week's strip, a column per weekday, blank for other months' days. */
const strip = computed(() =>
    (week.value?.days ?? [])
        .map((day, index) => ({ day, letter: 'MTWTFSS'[index] }))
        .slice(0, props.showWeekends ? 7 : 5),
);

const range = computed(() => {
    const shown = days.value;

    return shown.length > 0
        ? formatDateRange(shown[0].date, shown[shown.length - 1].date)
        : '';
});

function step(by: -1 | 1): void {
    const next = weekIndex.value + by;

    if (next >= 0 && next < props.weeks.length) {
        weekIndex.value = next;

        return;
    }

    arriving.value = by === -1 ? 'last' : 'first';
    emit('month', by);
}

/** The state the strip shows for a day: its first Work Entry's. */
function stripState(day: WorkEntryDay) {
    return day.entries[0]?.state ?? null;
}
</script>

<template>
    <div data-slot="week-agenda" class="flex flex-col gap-3">
        <div class="flex items-center gap-1">
            <span class="text-meta font-semibold">Week {{ week?.number }}</span>
            <span class="text-meta text-muted-foreground tabular-nums"
                >· {{ formatHours(week?.hours ?? 0) }}</span
            >
            <Button
                variant="ghost"
                size="icon-sm"
                class="ml-auto"
                aria-label="Previous week"
                @click="step(-1)"
                ><ChevronLeft
            /></Button>
            <span class="text-meta font-semibold whitespace-nowrap">{{
                range
            }}</span>
            <Button
                variant="ghost"
                size="icon-sm"
                aria-label="Next week"
                @click="step(1)"
                ><ChevronRight
            /></Button>
        </div>

        <div
            class="grid gap-1 text-center"
            :class="showWeekends ? 'grid-cols-7' : 'grid-cols-5'"
            aria-hidden="true"
        >
            <div
                v-for="({ day, letter }, index) in strip"
                :key="index"
                class="flex flex-col items-center gap-0.5 rounded-lg py-1.5"
                :class="
                    day && selectedDate === day.date
                        ? 'bg-primary-soft ring-2 ring-primary ring-inset'
                        : ''
                "
            >
                <span class="text-caption text-muted-foreground">{{
                    letter
                }}</span>
                <span
                    class="grid size-7 place-items-center rounded-full text-body font-semibold tabular-nums"
                    :class="[
                        day?.date === today &&
                            'bg-primary text-primary-foreground',
                        day && day.date > today && 'text-subtle-foreground',
                        !day && 'text-transparent',
                    ]"
                    >{{ day ? Number(day.date.slice(8)) : '·' }}</span
                >
                <span class="flex h-3.5 items-center">
                    <StatusIcon
                        v-if="day && stripState(day)"
                        :state="stripState(day)!"
                        labelled
                        class="[&>svg]:size-3"
                    />
                    <Sun
                        v-else-if="day?.time_off"
                        class="size-3 text-muted-foreground"
                    />
                </span>
            </div>
        </div>

        <ul class="overflow-hidden rounded-lg border">
            <li
                v-for="day in days"
                :key="day.date"
                class="border-b last:border-b-0"
            >
                <button
                    type="button"
                    class="flex w-full items-start gap-3 px-4 py-3 text-left text-body outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-inset"
                    :class="day.date === today ? 'bg-primary-soft' : ''"
                    :data-agenda-day="day.date"
                    :aria-label="formatLongDay(day.date)"
                    @click="emit('open', day.date)"
                >
                    <span
                        class="w-14 shrink-0 font-semibold"
                        :class="
                            day.date > today &&
                            'font-medium text-subtle-foreground'
                        "
                        >{{
                            new Date(`${day.date}T00:00:00`).toLocaleDateString(
                                'en',
                                { weekday: 'short' },
                            )
                        }}
                        {{ Number(day.date.slice(8)) }}</span
                    >
                    <span class="flex min-w-0 flex-1 flex-col gap-1">
                        <span
                            v-for="entry in day.entries"
                            :key="entry.id"
                            class="flex min-w-0 flex-col"
                        >
                            <span class="flex min-w-0 items-center gap-1.5">
                                <StatusIcon :state="entry.state" />
                                <span
                                    class="truncate"
                                    :class="
                                        entry.declined &&
                                        'text-muted-foreground line-through'
                                    "
                                    >{{ entry.description }}</span
                                >
                            </span>
                            <span
                                class="ml-5 text-meta text-muted-foreground"
                                >{{ entry.project }}</span
                            >
                        </span>
                        <span
                            v-if="day.time_off"
                            class="flex items-center gap-1.5 text-muted-foreground"
                            ><Sun class="size-3.5" aria-hidden="true" />{{
                                timeOffKindLabel(day.time_off)
                            }}</span
                        >
                        <span
                            v-else-if="day.entries.length === 0"
                            class="text-meta text-subtle-foreground"
                            >No Work Entries</span
                        >
                    </span>
                    <span
                        v-if="day.hours > 0"
                        class="shrink-0 font-semibold tabular-nums"
                        >{{ formatHours(day.hours) }}</span
                    >
                </button>
            </li>
        </ul>
    </div>
</template>

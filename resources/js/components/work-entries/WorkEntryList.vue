<script setup lang="ts">
import { Pencil } from '@lucide/vue';
import { computed, nextTick, reactive, ref } from 'vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import DeleteWorkEntry from '@/components/work-entries/DeleteWorkEntry.vue';
import WorkEntryRow from '@/components/work-entries/WorkEntryRow.vue';
import type {
    Choice,
    WorkEntryAssignment,
    WorkEntryDay,
    WorkEntryPeriod,
    WorkEntryRecord,
    WorkEntryWeek,
} from '@/types';

/**
 * The List tab: one table of the Period's Work Entries, a row per Work
 * Entry under a row per ISO week. A run of days with the same kind of Time
 * Off and nothing logged folds into one row, and so does a run of future
 * days with nothing logged. On a phone each Work Entry stacks into two
 * lines.
 */
const props = defineProps<{
    employee: Choice;
    period: WorkEntryPeriod;
    today: string;
    days: WorkEntryDay[];
    weeks: WorkEntryWeek[];
    assignments: WorkEntryAssignment[];
    canManage: boolean;
    showWeekends: boolean;
}>();

const emit = defineEmits<{
    /** Whether a row holds typing that isn't saved yet, by row key. */
    dirty: [rowKey: string, isDirty: boolean];
}>();

/**
 * A row of the table under a week: a day with its Work Entries, or a run
 * of days folded into one.
 */
type Segment =
    | { type: 'day'; day: WorkEntryDay }
    | {
          type: 'time-off';
          kind: 'paid' | 'unpaid';
          from: string;
          to: string;
          weekdays: number;
      }
    | { type: 'upcoming'; from: string; to: string };

const columns =
    'grid grid-cols-[minmax(0,1fr)_auto] gap-x-3 sm:grid-cols-[7rem_minmax(0,1fr)_minmax(0,12rem)_5.5rem_4.5rem_4.5rem]';

/**
 * Hours as people read them, such as "7.5 h".
 */
function formatHours(hours: number): string {
    return `${hours.toLocaleString(undefined, { maximumFractionDigits: 2 })} h`;
}

/**
 * A day's short name and number, such as "Thu 12".
 */
function formatDay(date: string): string {
    const day = new Date(`${date}T00:00:00`);

    return `${day.toLocaleDateString(undefined, { weekday: 'short' })} ${day.getDate()}`;
}

function formatShort(date: string): string {
    return new Date(`${date}T00:00:00`).toLocaleDateString(undefined, {
        day: 'numeric',
        month: 'short',
    });
}

/**
 * A run of days, such as "Mon 23 – Fri 27", or "Mon 23" for one day.
 */
function formatRun(from: string, to: string): string {
    return from === to
        ? formatDay(from)
        : `${formatDay(from)} – ${formatDay(to)}`;
}

/**
 * A week's dates, such as "2 Nov – 8 Nov", or "1 Nov" for a week the
 * Period holds one day of.
 */
function formatWeekDates(week: WorkEntryWeek): string {
    return week.from === week.to
        ? formatShort(week.from)
        : `${formatShort(week.from)} – ${formatShort(week.to)}`;
}

function isWeekend(date: string): boolean {
    const weekday = new Date(`${date}T00:00:00`).getDay();

    return weekday === 0 || weekday === 6;
}

/** The Assignment's Project and job title, such as "Relaunch · Tech Lead". */
function projectOf(entry: WorkEntryRecord): string {
    const jobTitle = props.assignments.find(
        (assignment) => assignment.id === entry.assignment_id,
    )?.job_title;

    return jobTitle ? `${entry.project} · ${jobTitle}` : entry.project;
}

function takesEntries(day: WorkEntryDay): boolean {
    return props.canManage && day.date <= props.today;
}

/**
 * The days listed: every day of the Period, or with weekends hidden, every
 * day but a Saturday or Sunday with no Work Entries, so hours are never out
 * of sight. Should that leave no day at all, every day is listed.
 */
const shownDays = computed(() => {
    if (props.showWeekends) {
        return props.days;
    }

    const shown = props.days.filter(
        (day) => !isWeekend(day.date) || day.entries.length > 0,
    );

    return shown.length > 0 ? shown : props.days;
});

/**
 * Folds a week's days into rows: consecutive days with the same kind of
 * Time Off and no Work Entries become one, counting only weekdays, and so
 * do consecutive future days with no Work Entries.
 */
function segmentsOf(days: WorkEntryDay[]): Segment[] {
    const segments: Segment[] = [];

    for (const day of days) {
        const last = segments.at(-1);

        if (day.entries.length > 0) {
            segments.push({ type: 'day', day });
        } else if (day.time_off !== null) {
            if (last?.type === 'time-off' && last.kind === day.time_off) {
                last.to = day.date;
                last.weekdays += 1;
            } else {
                segments.push({
                    type: 'time-off',
                    kind: day.time_off,
                    from: day.date,
                    to: day.date,
                    weekdays: 1,
                });
            }
        } else if (day.date > props.today) {
            if (last?.type === 'upcoming') {
                last.to = day.date;
            } else {
                segments.push({
                    type: 'upcoming',
                    from: day.date,
                    to: day.date,
                });
            }
        } else {
            segments.push({ type: 'day', day });
        }
    }

    return segments;
}

/**
 * The listed days, one block per week under a row naming it. A week left
 * with no day to list, because its only days are hidden weekends, has no
 * block.
 */
const blocks = computed(() =>
    props.weeks
        .map((week) => ({
            week,
            segments: segmentsOf(
                shownDays.value.filter(
                    (day) => day.date >= week.from && day.date <= week.to,
                ),
            ),
        }))
        .filter((block) => block.segments.length > 0),
);

function segmentKey(segment: Segment): string {
    return segment.type === 'day'
        ? segment.day.date
        : `${segment.type}-${segment.from}`;
}

/** The Work Entries turned into fields to change them. */
const editing = reactive(new Set<number>());

/** The Assignment of the Work Entry last added on this page. */
const lastAssignmentId = ref<number | null>(null);

/** Each day's empty row, by date, so the cursor can move between them. */
const newRows = new Map<string, InstanceType<typeof WorkEntryRow>>();

function rememberNewRow(date: string, row: unknown): void {
    if (row) {
        newRows.set(date, row as InstanceType<typeof WorkEntryRow>);
    } else {
        newRows.delete(date);
    }
}

/**
 * Whether the cursor stops at a day after adding a Work Entry: a weekday up
 * to today without Time Off.
 */
function takesNextEntry(day: WorkEntryDay): boolean {
    return (
        !isWeekend(day.date) && day.time_off === null && day.date <= props.today
    );
}

/**
 * After a Work Entry is added, moves the cursor to the next day that takes
 * one, or keeps it on the same day when none is left.
 */
async function entryAdded(date: string, assignmentId: number): Promise<void> {
    lastAssignmentId.value = assignmentId;

    const next = props.days.find(
        (day) => day.date > date && takesNextEntry(day),
    );

    await nextTick();
    newRows.get(next?.date ?? date)?.focusHours();
}
</script>

<template>
    <div class="overflow-hidden rounded-lg border text-sm">
        <div
            :class="columns"
            class="hidden bg-muted/50 px-4 py-2 font-medium text-muted-foreground sm:grid"
        >
            <span>Day</span>
            <span>What was done</span>
            <span>Project</span>
            <span>Status</span>
            <span class="text-right">Hours</span>
            <span><span class="sr-only">Actions</span></span>
        </div>

        <section
            v-for="block in blocks"
            :key="block.week.from"
            :aria-label="`Week ${block.week.number}`"
        >
            <h3
                class="flex items-center justify-between gap-4 border-t bg-muted/30 px-4 py-2 font-medium first:border-t-0 sm:first:border-t"
            >
                <span
                    >Week {{ block.week.number }}
                    <span class="font-normal text-muted-foreground"
                        >· {{ formatWeekDates(block.week) }}</span
                    ></span
                >
                <span class="tabular-nums">{{
                    formatHours(block.week.hours)
                }}</span>
            </h3>

            <template
                v-for="segment in block.segments"
                :key="segmentKey(segment)"
            >
                <div
                    v-if="segment.type === 'time-off'"
                    :class="columns"
                    class="items-center border-t px-4 py-2.5"
                    data-test="time-off-run"
                >
                    <span class="col-span-2 font-medium sm:col-span-1">{{
                        formatRun(segment.from, segment.to)
                    }}</span>
                    <span
                        class="col-span-2 flex items-center gap-2 sm:col-span-5"
                        ><span
                            class="rounded-full bg-muted px-2 py-0.5 text-xs font-medium"
                            >{{
                                segment.kind === 'paid'
                                    ? 'Paid Time Off'
                                    : 'Unpaid Time Off'
                            }}</span
                        ><span class="text-muted-foreground"
                            >{{ segment.weekdays }}
                            {{ segment.weekdays === 1 ? 'day' : 'days' }}</span
                        ></span
                    >
                </div>

                <div
                    v-else-if="segment.type === 'upcoming'"
                    :class="columns"
                    class="items-center border-t px-4 py-2.5"
                    data-test="upcoming-run"
                >
                    <span class="font-medium">{{
                        formatRun(segment.from, segment.to)
                    }}</span>
                    <span class="text-muted-foreground sm:col-span-5"
                        >Upcoming</span
                    >
                </div>

                <div v-else class="border-t" :data-date="segment.day.date">
                    <template
                        v-for="(entry, index) in segment.day.entries"
                        :key="entry.id"
                    >
                        <div
                            v-if="editing.has(entry.id)"
                            class="mx-2 my-2 rounded-md px-2 py-2 ring-2 ring-primary"
                        >
                            <WorkEntryRow
                                :employee="employee"
                                :period-query="period.query"
                                :assignments="assignments"
                                :date="segment.day.date"
                                :today="today"
                                :entry="entry"
                                @saved="editing.delete(entry.id)"
                                @cancel="editing.delete(entry.id)"
                                @dirty="
                                    emit('dirty', `entry-${entry.id}`, $event)
                                "
                            />
                        </div>
                        <div
                            v-else
                            :class="columns"
                            class="min-h-11 items-center gap-y-0.5 px-4 py-1.5 hover:bg-muted/40"
                            data-test="work-entry"
                        >
                            <span
                                :class="[
                                    'col-span-2 font-medium sm:col-span-1',
                                    index > 0 && 'hidden sm:block',
                                ]"
                                ><template v-if="index === 0"
                                    >{{ formatDay(segment.day.date)
                                    }}<span
                                        v-if="segment.day.date === today"
                                        class="ml-1 text-xs font-normal text-muted-foreground"
                                        >today</span
                                    ></template
                                ></span
                            >
                            <span class="truncate" :title="entry.description">{{
                                entry.description
                            }}</span>
                            <span
                                class="order-2 flex min-w-0 items-center gap-2 sm:contents"
                            >
                                <span class="truncate text-muted-foreground">{{
                                    projectOf(entry)
                                }}</span>
                                <span
                                    ><StatusBadge :state="entry.state"
                                /></span>
                            </span>
                            <span
                                class="order-1 text-right font-medium tabular-nums sm:order-none"
                                >{{ formatHours(entry.hours) }}</span
                            >
                            <span
                                class="order-3 flex justify-end sm:order-none"
                            >
                                <Button
                                    v-if="entry.can_edit"
                                    type="button"
                                    variant="ghost"
                                    size="icon-sm"
                                    class="text-muted-foreground"
                                    aria-label="Edit Work Entry"
                                    @click="editing.add(entry.id)"
                                    ><Pencil
                                /></Button>
                                <DeleteWorkEntry
                                    v-if="entry.can_delete"
                                    :entry="entry"
                                    :period-query="period.query"
                                />
                            </span>
                        </div>
                    </template>

                    <p
                        v-if="
                            segment.day.time_off &&
                            segment.day.entries.length > 0
                        "
                        class="px-4 pb-2"
                    >
                        <span
                            class="rounded-full bg-muted px-2 py-0.5 text-xs font-medium"
                            >{{
                                segment.day.time_off === 'paid'
                                    ? 'Paid Time Off'
                                    : 'Unpaid Time Off'
                            }}</span
                        >
                    </p>

                    <div
                        v-if="takesEntries(segment.day)"
                        :class="columns"
                        class="items-start px-4 py-2"
                    >
                        <span
                            :class="[
                                'col-span-2 font-medium sm:col-span-1 sm:pt-2',
                                segment.day.entries.length > 0 &&
                                    'hidden sm:block',
                            ]"
                            ><template v-if="segment.day.entries.length === 0"
                                >{{ formatDay(segment.day.date)
                                }}<span
                                    v-if="segment.day.date === today"
                                    class="ml-1 text-xs font-normal text-muted-foreground"
                                    >today</span
                                ></template
                            ></span
                        >
                        <div class="col-span-2 sm:col-span-5">
                            <WorkEntryRow
                                :ref="
                                    (row) =>
                                        rememberNewRow(segment.day.date, row)
                                "
                                :employee="employee"
                                :period-query="period.query"
                                :assignments="assignments"
                                :date="segment.day.date"
                                :today="today"
                                :last-assignment-id="lastAssignmentId"
                                @saved="entryAdded(segment.day.date, $event)"
                                @dirty="
                                    emit(
                                        'dirty',
                                        `new-${segment.day.date}`,
                                        $event,
                                    )
                                "
                            />
                        </div>
                    </div>
                    <div
                        v-else-if="segment.day.entries.length === 0"
                        :class="columns"
                        class="min-h-11 items-center px-4 py-1.5"
                    >
                        <span class="font-medium text-muted-foreground">{{
                            formatDay(segment.day.date)
                        }}</span>
                        <span
                            v-if="segment.day.nothing_logged"
                            class="text-muted-foreground italic sm:col-span-5"
                            >Nothing logged</span
                        >
                        <span v-else class="text-muted-foreground sm:col-span-5"
                            >—</span
                        >
                    </div>
                </div>
            </template>
        </section>
    </div>
</template>

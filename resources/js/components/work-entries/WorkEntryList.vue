<script setup lang="ts">
import {
    formatHours,
    takesEntries as takesEntriesOn,
} from '@/lib/work-entries';
import { ArrowRight, Pencil, Plus } from '@lucide/vue';
import { computed, nextTick, reactive, ref, watch } from 'vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import CopyWorkEntriesButton from '@/components/work-entries/CopyWorkEntriesButton.vue';
import DeleteWorkEntry from '@/components/work-entries/DeleteWorkEntry.vue';
import UnsavedWorkEntryDialog from '@/components/work-entries/UnsavedWorkEntryDialog.vue';
import WorkEntryRow from '@/components/work-entries/WorkEntryRow.vue';
import { formatLongDay, formatWeekdayAndDay, isWeekend } from '@/lib/dates';
import { formatDays, timeOffKindLabel } from '@/lib/time-off';
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
 * lines. The add form opens on one day at a time: from an empty day's
 * quiet row, or the + beside a day that has Work Entries.
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
        ? formatWeekdayAndDay(from)
        : `${formatWeekdayAndDay(from)} – ${formatWeekdayAndDay(to)}`;
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

/** Each Assignment's job title, by Assignment id. */
const jobTitles = computed(
    () =>
        new Map(
            props.assignments.map((assignment) => [
                assignment.id,
                assignment.job_title,
            ]),
        ),
);

/** The Assignment's Project and job title, such as "Relaunch · Tech Lead". */
function projectOf(entry: WorkEntryRecord): string {
    const jobTitle = jobTitles.value.get(entry.assignment_id);

    return jobTitle ? `${entry.project} · ${jobTitle}` : entry.project;
}

function takesEntries(day: WorkEntryDay): boolean {
    return takesEntriesOn(day, props.canManage, props.today);
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
 * do consecutive future days with no Work Entries. A day of Time Off the
 * viewer can still add hours to keeps its own row.
 */
function segmentsOf(days: WorkEntryDay[]): Segment[] {
    const segments: Segment[] = [];

    for (const day of days) {
        const last = segments.at(-1);

        if (day.entries.length > 0) {
            segments.push({ type: 'day', day });
        } else if (day.time_off !== null && !takesEntries(day)) {
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
        } else if (day.time_off === null && day.date > props.today) {
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

/** The day the add form is open on, if any; only one is open at a time. */
const activeDate = ref<string | null>(null);

/** What to do once giving up unsaved typing is confirmed. */
const heldAction = ref<(() => void) | null>(null);

/** The rows holding typing that isn't saved yet, by row key. */
const dirtyRows = reactive(new Set<string>());

let addRow: InstanceType<typeof WorkEntryRow> | null = null;
let addRowDate: string | null = null;

/**
 * Keeps hold of the open add form. When it moves to another day, the old
 * day's row may let go after the new one is held, so only the day held
 * lets go.
 */
function rememberAddRow(date: string, row: unknown): void {
    if (row) {
        addRow = row as InstanceType<typeof WorkEntryRow>;
        addRowDate = date;
    } else if (addRowDate === date) {
        addRow = null;
        addRowDate = null;
    }
}

function rowChanged(rowKey: string, isDirty: boolean): void {
    if (isDirty) {
        dirtyRows.add(rowKey);
    } else {
        dirtyRows.delete(rowKey);
    }

    emit('dirty', rowKey, isDirty);
}

/**
 * Does something that gives up a row's typing, asking first when it holds
 * any that isn't saved.
 */
function unlessUnsaved(rowKey: string, action: () => void): void {
    if (dirtyRows.has(rowKey)) {
        heldAction.value = action;
    } else {
        action();
    }
}

/**
 * Opens the add form on a day, closing the one open on another day; when
 * that one holds unsaved typing, asks first.
 */
function openAdd(date: string): void {
    if (activeDate.value === null || activeDate.value === date) {
        void showAdd(date);

        return;
    }

    unlessUnsaved(`new-${activeDate.value}`, () => void showAdd(date));
}

async function showAdd(date: string): Promise<void> {
    activeDate.value = date;
    await nextTick();
    addRow?.focusDescription();
}

function closeAdd(): void {
    activeDate.value = null;
}

/** Esc in the add form closes it, asking first when it holds unsaved typing. */
function dismissAdd(date: string): void {
    unlessUnsaved(`new-${date}`, closeAdd);
}

/** Esc while changing a Work Entry gives up, asking first when it was changed. */
function dismissEdit(entryId: number): void {
    unlessUnsaved(`entry-${entryId}`, () => editing.delete(entryId));
}

/**
 * Once the dialog asking about unsaved typing closes, puts the cursor back
 * in the open add form rather than on whatever opened the dialog.
 */
function refocusAddForm(event: Event): void {
    if (addRow !== null) {
        event.preventDefault();
        addRow.focusDescription();
    }
}

function leaveUnsaved(): void {
    const action = heldAction.value;
    heldAction.value = null;
    action?.();
}

/** A form open in one Period isn't carried into another. */
watch(
    () => JSON.stringify(props.period.query),
    () => {
        activeDate.value = null;
        editing.clear();
    },
);

/**
 * Whether "Next day" stops at a day: a weekday up to today without Time
 * Off.
 */
function takesNextEntry(day: WorkEntryDay): boolean {
    return (
        !isWeekend(day.date) && day.time_off === null && day.date <= props.today
    );
}

/** The day of the Period "Next day" moves the add form to, if any. */
const nextDay = computed(() => {
    const date = activeDate.value;

    return date === null
        ? null
        : (props.days.find((day) => day.date > date && takesNextEntry(day))
              ?.date ?? null);
});

/**
 * After a Work Entry is added, keeps the add form on the same day, empty,
 * with the cursor in What was done for the next one.
 */
async function entryAdded(assignmentId: number): Promise<void> {
    lastAssignmentId.value = assignmentId;
    await nextTick();
    addRow?.focusDescription();
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
            class="first-of-type:[&>h3]:border-t-0 sm:first-of-type:[&>h3]:border-t"
        >
            <h3
                class="flex items-center justify-between gap-4 border-t bg-muted/30 px-4 py-2 font-medium"
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
                            >{{ timeOffKindLabel(segment.kind) }}</span
                        ><span class="text-muted-foreground">{{
                            formatDays(segment.weekdays)
                        }}</span></span
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

                <div
                    v-else
                    class="group/day border-t"
                    :data-date="segment.day.date"
                >
                    <p v-if="segment.day.time_off" class="px-4 pt-2">
                        <span
                            class="rounded-full bg-muted px-2 py-0.5 text-xs font-medium"
                            >{{ timeOffKindLabel(segment.day.time_off) }}</span
                        >
                    </p>
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
                                @dismiss="dismissEdit(entry.id)"
                                @dirty="rowChanged(`entry-${entry.id}`, $event)"
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
                                    'col-span-2 flex items-center font-medium sm:col-span-1',
                                    index > 0 && 'hidden sm:flex',
                                ]"
                                ><template v-if="index === 0"
                                    >{{ formatWeekdayAndDay(segment.day.date)
                                    }}<span
                                        v-if="segment.day.date === today"
                                        class="ml-1 text-xs font-normal text-muted-foreground"
                                        >today</span
                                    ><span
                                        v-if="segment.day.entries.length > 1"
                                        class="ml-1 text-xs font-normal text-muted-foreground tabular-nums"
                                        data-test="day-hours"
                                        >{{
                                            formatHours(segment.day.hours)
                                        }}</span
                                    ><Button
                                        v-if="
                                            takesEntries(segment.day) &&
                                            activeDate !== segment.day.date
                                        "
                                        type="button"
                                        variant="ghost"
                                        size="icon-sm"
                                        class="ml-0.5 size-7 text-muted-foreground opacity-0 group-hover/day:opacity-100 focus-visible:opacity-100 [@media(hover:none)]:opacity-100"
                                        :aria-label="`Add another entry on ${formatLongDay(segment.day.date)}`"
                                        @click="openAdd(segment.day.date)"
                                        ><Plus /></Button></template
                            ></span>
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

                    <div
                        v-if="
                            takesEntries(segment.day) &&
                            activeDate === segment.day.date
                        "
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
                                >{{ formatWeekdayAndDay(segment.day.date)
                                }}<span
                                    v-if="segment.day.date === today"
                                    class="ml-1 text-xs font-normal text-muted-foreground"
                                    >today</span
                                ></template
                            ></span
                        >
                        <div
                            class="col-span-2 rounded-md p-2 ring-2 ring-primary sm:col-span-5"
                        >
                            <WorkEntryRow
                                :ref="
                                    (row) =>
                                        rememberAddRow(segment.day.date, row)
                                "
                                :employee="employee"
                                :period-query="period.query"
                                :assignments="assignments"
                                :date="segment.day.date"
                                :today="today"
                                :last-assignment-id="lastAssignmentId"
                                @saved="entryAdded"
                                @cancel="closeAdd"
                                @dismiss="dismissAdd(segment.day.date)"
                                @dirty="
                                    rowChanged(
                                        `new-${segment.day.date}`,
                                        $event,
                                    )
                                "
                            />
                            <div
                                class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-muted-foreground"
                            >
                                <span
                                    ><kbd
                                        class="rounded border border-b-2 bg-background px-1 font-sans"
                                        >↵</kbd
                                    >
                                    add</span
                                >
                                <span
                                    ><kbd
                                        class="rounded border border-b-2 bg-background px-1 font-sans"
                                        >Esc</kbd
                                    >
                                    close</span
                                >
                                <span class="flex-1" />
                                <CopyWorkEntriesButton
                                    v-if="segment.day.copy_from"
                                    :employee="employee"
                                    :period-query="period.query"
                                    :date="segment.day.date"
                                    :copy-from="segment.day.copy_from"
                                />
                                <Button
                                    v-if="nextDay"
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    class="h-7"
                                    @click="openAdd(nextDay)"
                                    >Next day · {{ formatWeekdayAndDay(nextDay)
                                    }}<ArrowRight
                                /></Button>
                            </div>
                        </div>
                    </div>
                    <button
                        v-else-if="
                            takesEntries(segment.day) &&
                            segment.day.entries.length === 0
                        "
                        type="button"
                        :class="columns"
                        class="min-h-11 w-full items-center px-4 py-1.5 text-left outline-none hover:bg-muted/40 focus-visible:bg-muted/40"
                        data-test="add-row"
                        @click="openAdd(segment.day.date)"
                    >
                        <span class="font-medium"
                            >{{ formatWeekdayAndDay(segment.day.date)
                            }}<span
                                v-if="segment.day.date === today"
                                class="ml-1 text-xs font-normal text-muted-foreground"
                                >today</span
                            ></span
                        >
                        <span class="text-muted-foreground sm:col-span-5"
                            >Add what you worked on…</span
                        >
                    </button>
                    <div
                        v-else-if="segment.day.entries.length === 0"
                        :class="columns"
                        class="min-h-11 items-center px-4 py-1.5"
                    >
                        <span class="font-medium text-muted-foreground">{{
                            formatWeekdayAndDay(segment.day.date)
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

        <UnsavedWorkEntryDialog
            :open="heldAction !== null"
            @stay="heldAction = null"
            @leave="leaveUnsaved"
            @close-auto-focus="refocusAddForm"
        />
    </div>
</template>

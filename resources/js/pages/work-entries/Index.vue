<script setup lang="ts">
import { Head, router, setLayoutProps } from '@inertiajs/vue3';
import { useLocalStorage } from '@vueuse/core';
import { computed, nextTick, onMounted, onUnmounted, reactive, ref } from 'vue';
import Heading from '@/components/Heading.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import MonthPicker from '@/components/MonthPicker.vue';
import DeleteWorkEntry from '@/components/work-entries/DeleteWorkEntry.vue';
import PeriodNavigation from '@/components/work-entries/PeriodNavigation.vue';
import WorkEntryCalendar from '@/components/work-entries/WorkEntryCalendar.vue';
import WorkEntryRow from '@/components/work-entries/WorkEntryRow.vue';
import { index } from '@/routes/work-entries';
import type {
    Choice,
    WorkEntryAssignment,
    WorkEntryAssignmentTotal,
    WorkEntryCalendarWeek,
    WorkEntryDay,
    WorkEntriesTab,
    WorkEntryPeriod,
    WorkEntryWeek,
} from '@/types';

/**
 * Only the open tab's props are sent: the days, weeks, Assignment totals
 * and Assignments on the List tab, `calendar` on the Calendar tab, which
 * always shows a month.
 */
const props = defineProps<{
    tab: WorkEntriesTab;
    employee: Choice;
    period: WorkEntryPeriod;
    today: string;
    days?: WorkEntryDay[];
    weeks?: WorkEntryWeek[];
    assignmentTotals?: WorkEntryAssignmentTotal[];
    total: number;
    assignments?: WorkEntryAssignment[];
    calendar?: WorkEntryCalendarWeek[];
    canManage: boolean;
    /** Whether the week is the viewer's own. */
    isOwn: boolean;
}>();

/** "My Work Entries" for the viewer's own week, "Ben's Work Entries" for another Employee's. */
const title = computed(() =>
    props.isOwn ? 'My Work Entries' : `${props.employee.name}'s Work Entries`,
);

/** The breadcrumb opens the current week of whoever's week this is, so it holds whichever week is shown. */
setLayoutProps({
    breadcrumbs: [
        {
            title: title.value,
            href: index(
                props.isOwn ? {} : { query: { employee: props.employee.id } },
            ),
        },
    ],
});

/**
 * Hours as people read them, such as "7.5 h".
 */
function formatHours(hours: number): string {
    return `${hours.toLocaleString(undefined, { maximumFractionDigits: 2 })} h`;
}

/**
 * A day's name and date, such as "Mon 9 Nov".
 */
function formatDay(date: string): string {
    return new Date(`${date}T00:00:00`).toLocaleDateString(undefined, {
        weekday: 'short',
        day: 'numeric',
        month: 'short',
    });
}

/**
 * Shows another Period, naming the Employee only when the Work Entries
 * aren't the viewer's own.
 */
function showPeriod(query: Record<string, string>): void {
    router.visit(
        index({
            query: props.isOwn
                ? query
                : { employee: props.employee.id, ...query },
        }),
    );
}

/**
 * Opens a tab on the same Period: the Calendar on the month holding its
 * first day, the List on the Calendar's month.
 */
function openTab(tab: string | number): void {
    showPeriod(
        tab === 'calendar'
            ? { tab: 'calendar', month: props.period.from.slice(0, 7) }
            : props.period.query,
    );
}

/** The Work Entries turned into fields to change them. */
const editing = reactive(new Set<number>());

/** The rows holding typing that isn't saved yet, by row key. */
const unsaved = reactive(new Set<string>());

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

function markUnsaved(rowKey: string, isDirty: boolean): void {
    if (isDirty) {
        unsaved.add(rowKey);
    } else {
        unsaved.delete(rowKey);
    }
}

function stopEditing(entryId: number): void {
    editing.delete(entryId);
    unsaved.delete(`entry-${entryId}`);
}

/** Whether Saturdays and Sundays are shown, remembered in this browser. */
const showWeekends = useLocalStorage('work-entries.show-weekends', true);

/**
 * The days listed: every day of the Period, or with weekends hidden, every
 * day but a Saturday or Sunday with no Work Entries, so hours are never out
 * of sight. Should that leave no day at all, every day is listed.
 */
const shownDays = computed(() => {
    const days = props.days ?? [];

    if (showWeekends.value) {
        return days;
    }

    const shown = days.filter(
        (day) => !isWeekend(day.date) || day.entries.length > 0,
    );

    return shown.length > 0 ? shown : days;
});

/**
 * The listed days, one block per week under a header naming it. A week
 * left with no day to list, because its only days are hidden weekends,
 * has no block.
 */
const blocks = computed(() =>
    (props.weeks ?? [])
        .map((week) => ({
            week,
            days: shownDays.value.filter(
                (day) => day.date >= week.from && day.date <= week.to,
            ),
        }))
        .filter((block) => block.days.length > 0),
);

/**
 * A week's header, such as "Week 45 · 2–8 Nov · 37.5 h", or "Week 44 ·
 * 1 Nov · 0 h" for a week the Period holds one day of.
 */
function formatWeek(week: WorkEntryWeek): string {
    const short = (date: string): string =>
        new Date(`${date}T00:00:00`).toLocaleDateString(undefined, {
            day: 'numeric',
            month: 'short',
        });
    const dates =
        week.from === week.to
            ? short(week.from)
            : `${short(week.from)} – ${short(week.to)}`;

    return `Week ${week.number} · ${dates} · ${formatHours(week.hours)}`;
}

function isWeekend(date: string): boolean {
    const weekday = new Date(`${date}T00:00:00`).getDay();

    return weekday === 0 || weekday === 6;
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
 * one, or keeps it on the same day when none is left this week.
 */
async function entryAdded(date: string, assignmentId: number): Promise<void> {
    lastAssignmentId.value = assignmentId;

    const next = (props.days ?? []).find(
        (day) => day.date > date && takesNextEntry(day),
    );

    await nextTick();
    newRows.get(next?.date ?? date)?.focusHours();
}

/** A visit to another week or page held back until leaving is confirmed. */
const heldVisit = ref<URL | null>(null);

let stopGuarding: (() => void) | null = null;

onMounted(() => {
    stopGuarding = router.on('before', (event) => {
        const visit = event.detail.visit;

        if (
            visit.method !== 'get' ||
            visit.prefetch ||
            unsaved.size === 0 ||
            heldVisit.value !== null
        ) {
            return;
        }

        heldVisit.value = visit.url;

        return false;
    });
});

onUnmounted(() => stopGuarding?.());

function stayOnWeek(): void {
    heldVisit.value = null;
}

function leaveWeek(): void {
    const url = heldVisit.value;

    if (url === null) {
        return;
    }

    unsaved.clear();
    heldVisit.value = null;
    router.visit(url);
}
</script>

<template>
    <Head :title="title" />

    <div class="flex max-w-4xl flex-col gap-6 p-4">
        <Heading
            :title="title"
            :description="`Hours ${employee.name} worked`"
        />

        <Tabs :model-value="tab" @update:model-value="openTab">
            <TabsList class="self-start">
                <TabsTrigger value="list">List</TabsTrigger>
                <TabsTrigger value="calendar">Calendar</TabsTrigger>
            </TabsList>
        </Tabs>

        <div class="flex flex-wrap items-center justify-between gap-3">
            <PeriodNavigation
                v-if="tab === 'list'"
                :period="period"
                :today="today"
                @change="showPeriod"
            />
            <MonthPicker
                v-else
                :month="period.from.slice(0, 7)"
                allow-future
                @change="(month) => showPeriod({ tab: 'calendar', month })"
            />
            <div class="flex items-center gap-4">
                <Label class="flex items-center gap-2 text-sm font-normal">
                    <Checkbox
                        :model-value="showWeekends"
                        @update:model-value="showWeekends = $event === true"
                    />
                    Show weekends
                </Label>
                <span class="text-sm text-muted-foreground"
                    >Total
                    <span class="font-medium text-foreground tabular-nums">{{
                        formatHours(total)
                    }}</span></span
                >
            </div>
        </div>

        <WorkEntryCalendar
            v-if="tab === 'calendar' && calendar"
            :weeks="calendar"
            :today="today"
            :show-weekends="showWeekends"
        />

        <div v-if="tab === 'list'" class="flex flex-col gap-6">
            <section
                v-for="block in blocks"
                :key="block.week.from"
                class="space-y-2"
            >
                <h2 class="text-sm font-medium">
                    {{ formatWeek(block.week) }}
                </h2>
                <ul class="divide-y overflow-hidden rounded-lg border">
                    <li v-for="day in block.days" :key="day.date">
                        <div
                            class="flex items-center justify-between gap-4 bg-muted/50 px-4 py-2"
                        >
                            <p class="font-medium">
                                {{ formatDay(day.date) }}
                                <span
                                    class="ml-2 text-sm text-muted-foreground"
                                    >{{ formatHours(day.hours) }}</span
                                >
                                <span
                                    v-if="day.time_off"
                                    class="ml-2 rounded-full bg-muted px-2 py-0.5 text-xs font-medium"
                                    >{{
                                        day.time_off === 'paid'
                                            ? 'Paid Time Off'
                                            : 'Unpaid Time Off'
                                    }}</span
                                >
                                <span
                                    v-else-if="day.nothing_logged"
                                    class="ml-2 text-xs text-muted-foreground italic"
                                    >Nothing logged</span
                                >
                            </p>
                        </div>
                        <div
                            v-if="
                                day.entries.length > 0 ||
                                (canManage && day.date <= today)
                            "
                            class="space-y-2 border-t px-4 py-3"
                        >
                            <ul v-if="day.entries.length > 0" class="space-y-1">
                                <li
                                    v-for="entry in day.entries"
                                    :key="entry.id"
                                    class="text-sm"
                                >
                                    <WorkEntryRow
                                        v-if="editing.has(entry.id)"
                                        :employee="employee"
                                        :period-query="period.query"
                                        :assignments="assignments!"
                                        :date="day.date"
                                        :today="today"
                                        :entry="entry"
                                        @saved="stopEditing(entry.id)"
                                        @cancel="stopEditing(entry.id)"
                                        @dirty="
                                            markUnsaved(
                                                `entry-${entry.id}`,
                                                $event,
                                            )
                                        "
                                    />
                                    <div
                                        v-else
                                        class="flex items-center justify-between gap-4"
                                    >
                                        <p>
                                            <span class="font-medium">{{
                                                entry.project
                                            }}</span>
                                            <span class="text-muted-foreground">
                                                · {{ entry.description }}</span
                                            >
                                        </p>
                                        <div class="flex items-center gap-2">
                                            <StatusBadge :state="entry.state" />
                                            <span class="tabular-nums">{{
                                                formatHours(entry.hours)
                                            }}</span>
                                            <Button
                                                v-if="entry.can_edit"
                                                variant="ghost"
                                                size="sm"
                                                @click="editing.add(entry.id)"
                                                >Edit</Button
                                            >
                                            <DeleteWorkEntry
                                                v-else-if="entry.can_delete"
                                                :entry="entry"
                                                :period-query="period.query"
                                            />
                                        </div>
                                    </div>
                                </li>
                            </ul>
                            <WorkEntryRow
                                v-if="canManage && day.date <= today"
                                :ref="(row) => rememberNewRow(day.date, row)"
                                :employee="employee"
                                :period-query="period.query"
                                :assignments="assignments!"
                                :date="day.date"
                                :today="today"
                                :last-assignment-id="lastAssignmentId"
                                @saved="entryAdded(day.date, $event)"
                                @dirty="markUnsaved(`new-${day.date}`, $event)"
                            />
                        </div>
                    </li>
                </ul>
            </section>
        </div>

        <section
            v-if="assignmentTotals && assignmentTotals.length > 0"
            class="space-y-2"
        >
            <h2 class="text-sm font-medium">By Project</h2>
            <ul class="divide-y rounded-lg border text-sm">
                <li
                    v-for="assignmentTotal in assignmentTotals"
                    :key="assignmentTotal.assignment_id"
                    class="flex justify-between px-4 py-2"
                >
                    <span>{{ assignmentTotal.project }}</span>
                    <span class="tabular-nums">{{
                        formatHours(assignmentTotal.hours)
                    }}</span>
                </li>
            </ul>
        </section>

        <Dialog
            :open="heldVisit !== null"
            @update:open="(open) => !open && stayOnWeek()"
        >
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Leave without saving?</DialogTitle>
                    <DialogDescription>
                        You have an unsaved Work Entry. Leave anyway?
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter class="gap-2">
                    <Button variant="secondary" @click="stayOnWeek"
                        >Stay</Button
                    >
                    <Button variant="destructive" @click="leaveWeek"
                        >Leave</Button
                    >
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>

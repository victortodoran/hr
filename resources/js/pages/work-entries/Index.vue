<script setup lang="ts">
import { Head, router, setLayoutProps } from '@inertiajs/vue3';
import { useLocalStorage } from '@vueuse/core';
import { computed, reactive } from 'vue';
import Heading from '@/components/Heading.vue';
import PageContainer from '@/components/PageContainer.vue';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import MonthPicker from '@/components/MonthPicker.vue';
import UnsavedWorkEntryDialog from '@/components/work-entries/UnsavedWorkEntryDialog.vue';
import PeriodNavigation from '@/components/PeriodNavigation.vue';
import WorkEntryCalendar from '@/components/work-entries/WorkEntryCalendar.vue';
import WorkEntryList from '@/components/work-entries/WorkEntryList.vue';
import WorkEntrySummary from '@/components/work-entries/WorkEntrySummary.vue';
import { useUnsavedChanges } from '@/composables/useUnsavedChanges';
import { index } from '@/routes/work-entries';
import type {
    Choice,
    WorkEntryAssignment,
    WorkEntryAssignmentTotal,
    WorkEntryCalendarWeek,
    WorkEntryDay,
    WorkEntriesTab,
    Period,
    WorkEntryWeek,
} from '@/types';

/**
 * Only the open tab's props are sent: the days and weeks on the List tab,
 * `calendar` on the Calendar tab, which always shows a month. Both get the
 * Period's total, the hours on each Assignment and the Assignments to add
 * to.
 */
const props = defineProps<{
    tab: WorkEntriesTab;
    employee: Choice;
    period: Period;
    today: string;
    days?: WorkEntryDay[];
    weeks?: WorkEntryWeek[];
    assignmentTotals: WorkEntryAssignmentTotal[];
    total: number;
    /** The hours of the Period's Declined Work Entries, which count nowhere. */
    declinedHours: number;
    assignments: WorkEntryAssignment[];
    calendar?: WorkEntryCalendarWeek[];
    canManage: boolean;
    /** Whether the week is the viewer's own. */
    isOwn: boolean;
}>();

/** "Work" for the viewer's own week, "Ben's Work Entries" for another Employee's. */
const title = computed(() =>
    props.isOwn ? 'Work' : `${props.employee.name}'s Work Entries`,
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
 * Opens a tab on the same Period: the Calendar on the month holding today
 * when the Period does, or else its first day; the List on the Calendar's
 * month.
 */
function openTab(tab: string | number): void {
    const { from, to, query } = props.period;
    const anchor =
        from <= props.today && props.today <= to ? props.today : from;

    showPeriod(
        tab === 'calendar'
            ? { tab: 'calendar', month: anchor.slice(0, 7) }
            : query,
    );
}

/** The rows holding typing that isn't saved yet, by row key. */
const unsaved = reactive(new Set<string>());

function markUnsaved(rowKey: string, isDirty: boolean): void {
    if (isDirty) {
        unsaved.add(rowKey);
    } else {
        unsaved.delete(rowKey);
    }
}

/** Whether Saturdays and Sundays are shown, remembered in this browser. */
const showWeekends = useLocalStorage('work-entries.show-weekends', true);

/**
 * Asks before leaving with a Work Entry typed but not saved, whether for
 * another week, another page, a reload or closing the tab.
 */
const guard = useUnsavedChanges(
    computed(() => unsaved.size > 0),
    () => unsaved.clear(),
);
</script>

<template>
    <Head :title="title" />

    <PageContainer width="wide">
        <Heading
            :title="title"
            :description="`Hours ${employee.name} worked`"
        />

        <div class="flex flex-wrap items-center gap-3">
            <Tabs :model-value="tab" @update:model-value="openTab">
                <TabsList>
                    <TabsTrigger value="list">List</TabsTrigger>
                    <TabsTrigger value="calendar">Calendar</TabsTrigger>
                </TabsList>
            </Tabs>
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
            <Label class="ml-auto flex items-center gap-2 text-sm font-normal">
                <Checkbox
                    :model-value="showWeekends"
                    @update:model-value="showWeekends = $event === true"
                />
                Show weekends
            </Label>
        </div>

        <WorkEntrySummary
            :period="period"
            :total="total"
            :declined-hours="declinedHours"
            :assignment-totals="assignmentTotals"
            :week-number="weeks?.[0]?.number"
        />

        <WorkEntryCalendar
            v-if="tab === 'calendar' && calendar"
            :weeks="calendar"
            :today="today"
            :show-weekends="showWeekends"
            :employee="employee"
            :period="period"
            :assignments="assignments"
            :can-manage="canManage"
            @dirty="markUnsaved"
        />

        <WorkEntryList
            v-if="tab === 'list' && days && weeks"
            :employee="employee"
            :period="period"
            :today="today"
            :days="days"
            :weeks="weeks"
            :assignments="assignments"
            :can-manage="canManage"
            :show-weekends="showWeekends"
            @dirty="markUnsaved"
        />

        <UnsavedWorkEntryDialog
            :open="guard.pending.value !== null"
            @stay="guard.keep"
            @leave="guard.discard"
        />
    </PageContainer>
</template>

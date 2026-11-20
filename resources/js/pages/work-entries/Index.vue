<script setup lang="ts">
import { Head, Link, router, setLayoutProps, usePage } from '@inertiajs/vue3';
import { LayoutGrid, List, Plus, UserX } from '@lucide/vue';
import { useLocalStorage } from '@vueuse/core';
import { computed, nextTick, reactive, ref } from 'vue';
import EmptyState from '@/components/EmptyState.vue';
import ExportMenu from '@/components/ExportMenu.vue';
import MonthPicker from '@/components/MonthPicker.vue';
import PageContainer from '@/components/PageContainer.vue';
import PageHeader from '@/components/PageHeader.vue';
import PeriodNavigation from '@/components/PeriodNavigation.vue';
import ScopeSwitch from '@/components/ScopeSwitch.vue';
import SegmentedControl from '@/components/SegmentedControl.vue';
import StatStrip from '@/components/StatStrip.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import UnsavedWorkEntryDialog from '@/components/work-entries/UnsavedWorkEntryDialog.vue';
import WorkEntryCalendar from '@/components/work-entries/WorkEntryCalendar.vue';
import WorkEntryList from '@/components/work-entries/WorkEntryList.vue';
import { useUnsavedChanges } from '@/composables/useUnsavedChanges';
import { addMonths } from '@/lib/dates';
import { describePeriod, formatHours } from '@/lib/work-entries';
import { index } from '@/routes/work-entries';
import { show as exportFile } from '@/routes/work-entry-exports';
import { index as workEntryList } from '@/routes/work-entry-list';
import type {
    Choice,
    Period,
    WorkEntriesTab,
    WorkEntryAssignment,
    WorkEntryAssignmentTotal,
    WorkEntryCalendarWeek,
    WorkEntryDay,
    WorkEntryWeek,
} from '@/types';

/** Only the open view's props are sent. */
const props = defineProps<{
    tab: WorkEntriesTab;
    employee: Choice;
    period: Period;
    today: string;
    days?: WorkEntryDay[];
    weeks?: WorkEntryWeek[];
    assignmentTotals: WorkEntryAssignmentTotal[];
    total: number;
    approvedHours: number;
    pendingHours: number;
    declinedHours: number;
    manager: Choice | null;
    assignments: WorkEntryAssignment[];
    calendar?: WorkEntryCalendarWeek[];
    canManage: boolean;
    isOwn: boolean;
}>();

const page = usePage();

const title = computed(() =>
    props.isOwn ? 'Work' : `${props.employee.name}'s Work Entries`,
);

const description = computed(() => {
    const whose = props.isOwn
        ? 'Your Work Entries'
        : `${props.employee.name}'s Work Entries`;

    return props.manager
        ? `${whose} by day. ${props.manager.name} approves each one.`
        : `${whose} by day.`;
});

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

/** `keep` keeps the page's own state, so the phone's week agenda knows which end of the new month to start on. */
function showPeriod(query: Record<string, string>, keep = false): void {
    router.visit(
        index({
            query: props.isOwn
                ? query
                : { employee: props.employee.id, ...query },
        }),
        { preserveState: keep },
    );
}

function openTab(tab: WorkEntriesTab): void {
    const { from, to, query } = props.period;
    const anchor =
        from <= props.today && props.today <= to ? props.today : from;

    showPeriod(
        tab === 'calendar'
            ? { tab: 'calendar', month: anchor.slice(0, 7) }
            : query,
    );
}

const views = [
    { value: 'list' as const, label: 'List', icon: List },
    { value: 'calendar' as const, label: 'Calendar', icon: LayoutGrid },
];

const unsaved = reactive(new Set<string>());

function markUnsaved(rowKey: string, isDirty: boolean): void {
    if (isDirty) {
        unsaved.add(rowKey);
    } else {
        unsaved.delete(rowKey);
    }
}

const showWeekends = useLocalStorage('work-entries.show-weekends', true);

const guard = useUnsavedChanges(
    computed(() => unsaved.size > 0),
    () => unsaved.clear(),
);

const stats = computed(() => [
    {
        label: `Logged ${describePeriod(props.period, props.weeks?.[0]?.number)}`,
        value: formatHours(props.total),
    },
    {
        label: 'Approved',
        value: formatHours(props.approvedHours),
        state: 'approved' as const,
    },
    {
        label: 'Pending',
        value: formatHours(props.pendingHours),
        state: 'pending' as const,
    },
    ...(props.declinedHours > 0
        ? [
              {
                  label: 'Declined, not counted',
                  value: formatHours(props.declinedHours),
                  state: 'declined' as const,
                  muted: true,
              },
          ]
        : []),
    ...props.assignmentTotals.slice(0, 2).map((total) => ({
        label: total.project,
        value: formatHours(total.hours),
    })),
]);

const recordsNone = computed(
    () =>
        props.manager === null &&
        props.total === 0 &&
        props.declinedHours === 0,
);

const othersReach = computed(() => page.props.auth.othersReach.work);

function fileUrl(format: 'csv' | 'xlsx'): string {
    return exportFile.url({
        query: {
            ...props.period.query,
            employee: String(props.employee.id),
            format,
        },
    });
}

const calendarView = ref<InstanceType<typeof WorkEntryCalendar> | null>(null);
const listView = ref<InstanceType<typeof WorkEntryList> | null>(null);

const showsToday = computed(
    () => props.period.from <= props.today && props.today <= props.period.to,
);

async function addToday(): Promise<void> {
    if (!showsToday.value) {
        router.visit(
            index({
                query: {
                    ...(props.isOwn ? {} : { employee: props.employee.id }),
                    ...(props.tab === 'calendar'
                        ? { tab: 'calendar', month: props.today.slice(0, 7) }
                        : {}),
                },
            }),
            {
                preserveState: true,
                onSuccess: async () => {
                    await nextTick();
                    openToday();
                },
            },
        );

        return;
    }

    openToday();
}

function openToday(): void {
    if (props.tab === 'calendar') {
        calendarView.value?.show(props.today);
    } else {
        listView.value?.addOn(props.today);
    }
}

function stepMonth(step: -1 | 1): void {
    showPeriod(
        {
            tab: 'calendar',
            month: addMonths(props.period.from.slice(0, 7), step),
        },
        true,
    );
}
</script>

<template>
    <Head :title="title" />

    <PageContainer width="full">
        <PageHeader :title="title" :description="description">
            <template v-if="!recordsNone" #actions>
                <ExportMenu
                    :file-url="fileUrl"
                    :disabled="total === 0 && declinedHours === 0"
                />
                <Button v-if="canManage" @click="addToday"
                    ><Plus />Add Work Entry</Button
                >
            </template>
        </PageHeader>
        <ScopeSwitch area="work" />

        <EmptyState
            v-if="recordsNone"
            :icon="UserX"
            :message="
                isOwn
                    ? 'You have no Manager, so you don\'t record Work Entries: nobody could approve them.'
                    : `${employee.name} has no Manager, so they don't record Work Entries: nobody could approve them.`
            "
        >
            <template v-if="isOwn && othersReach" #action>
                <Button variant="secondary" as-child>
                    <Link :href="workEntryList()">{{
                        othersReach === 'everyone'
                            ? "See everyone's Work"
                            : "See your Reports' Work"
                    }}</Link>
                </Button>
            </template>
        </EmptyState>

        <template v-else>
            <div class="flex flex-wrap items-center gap-3">
                <SegmentedControl
                    :model-value="tab"
                    :options="views"
                    label="View"
                    @update:model-value="openTab"
                />
                <PeriodNavigation
                    v-if="tab === 'list'"
                    :period="period"
                    :today="today"
                    @change="showPeriod"
                />
                <MonthPicker
                    v-else
                    class="max-sm:hidden"
                    :month="period.from.slice(0, 7)"
                    allow-future
                    @change="(month) => showPeriod({ tab: 'calendar', month })"
                />
                <Label
                    class="ml-auto flex items-center gap-2 text-meta font-normal text-muted-foreground"
                >
                    <Switch
                        :model-value="showWeekends"
                        @update:model-value="showWeekends = $event"
                    />
                    Show weekends
                </Label>
            </div>

            <StatStrip :stats="stats" />

            <WorkEntryCalendar
                v-if="tab === 'calendar' && calendar"
                ref="calendarView"
                :weeks="calendar"
                :today="today"
                :show-weekends="showWeekends"
                :employee="employee"
                :period="period"
                :assignments="assignments"
                :can-manage="canManage"
                @dirty="markUnsaved"
                @month="stepMonth"
            />

            <WorkEntryList
                v-if="tab === 'list' && days && weeks"
                ref="listView"
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
        </template>

        <UnsavedWorkEntryDialog
            :open="guard.pending.value !== null"
            @stay="guard.keep"
            @leave="guard.discard"
        />
    </PageContainer>
</template>

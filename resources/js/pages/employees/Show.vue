<script setup lang="ts">
import { Head, Link, router, setLayoutProps } from '@inertiajs/vue3';
import { useMediaQuery } from '@vueuse/core';
import {
    Briefcase,
    Building2,
    CalendarDays,
    ChevronDown,
    ChevronRight,
    FolderKanban,
    Mail,
    Pencil,
    Plus,
    Target,
    UserRound,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import AssignmentController from '@/actions/App/Http/Controllers/Assignments/AssignmentController';
import AssignmentForm from '@/components/assignments/AssignmentForm.vue';
import CapacityBar from '@/components/assignments/CapacityBar.vue';
import Callout from '@/components/Callout.vue';
import ConfirmDeleteDialog from '@/components/ConfirmDeleteDialog.vue';
import DataTable from '@/components/DataTable.vue';
import EmployeePageSection from '@/components/employees/EmployeePageSection.vue';
import EmployeeTravelTab from '@/components/employees/EmployeeTravelTab.vue';
import EmployeeWorkTab from '@/components/employees/EmployeeWorkTab.vue';
import EmploymentStatusBadge from '@/components/employees/EmploymentStatusBadge.vue';
import ReportingChain from '@/components/employees/ReportingChain.vue';
import EmptyState from '@/components/EmptyState.vue';
import FactList from '@/components/FactList.vue';
import IconButton from '@/components/IconButton.vue';
import PageContainer from '@/components/PageContainer.vue';
import PageHeader from '@/components/PageHeader.vue';
import PayHistoryRow from '@/components/pay/PayHistoryRow.vue';
import PersonCell from '@/components/PersonCell.vue';
import StatStrip from '@/components/StatStrip.vue';
import RowActions from '@/components/RowActions.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import TextLink from '@/components/TextLink.vue';
import PaidDaysDialog from '@/components/time-off/PaidDaysDialog.vue';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import {
    DropdownMenuItem,
    DropdownMenuSeparator,
} from '@/components/ui/dropdown-menu';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import ValueOr from '@/components/ValueOr.vue';
import { getInitials } from '@/composables/useInitials';
import { formatDate, formatDateRange, formatPeriod } from '@/lib/dates';
import { outcomes } from '@/lib/outcomes';
import { formatPay, payKindNames, payPeriods } from '@/lib/pay';
import { formatDays } from '@/lib/time-off';
import { edit, show } from '@/routes/employees';
import { show as performanceEvaluationPage } from '@/routes/performance-evaluations';
import type {
    AssignmentRecord,
    AssignmentStats,
    Choice,
    EmployeePageRecord,
    EmployeePageTab,
    EmployeeStanding,
    EmployeeTimeOff,
    ListedTravelRequest,
    ListedWorkEntry,
    OpenProject,
    OwnPerformanceEvaluation,
    Paginated,
    PayEntryRecord,
    Period,
    ReportingChainView,
    ReportNeedingAManager,
    TableColumn,
} from '@/types';

/**
 * An Employee's page (rule L12): the header with their facts on every tab,
 * an attention Callout while Employees still report to them after they
 * left, then a tab per section. Only the open tab's props are sent:
 * `reportingChain` on Overview, `assignments` and `projects` on
 * Assignments & Pay, `paidDays` and `timeOff` on Time Off, `work` on Work,
 * `travel` on Travel and `performanceEvaluations` on Performance.
 */
const props = withDefaults(
    defineProps<{
        employee: Choice & { job_title: string | null };
        tab: EmployeePageTab;
        /** The tabs the viewer may open, Overview always first. */
        tabs: EmployeePageTab[];
        /** Null when the viewer may not see the Employee's record. */
        record: EmployeePageRecord | null;
        /** Those still reporting to the Employee after they left. */
        reportsNeedingAManager: ReportNeedingAManager[];
        /** Null for a Former Employee, who is no longer in the reporting line. */
        reportingChain?: ReportingChainView | null;
        /** Earliest first, those that have ended last. */
        assignments?: AssignmentRecord[];
        assignmentStats?: AssignmentStats;
        projects?: OpenProject[];
        canManage: boolean;
        canViewPay: boolean;
        canManagePay: boolean;
        /** The company currency; only sent to those who may manage Pay. */
        currency?: string;
        canEdit: boolean;
        /** Null when the viewer may not see the Employee's record. */
        standing: EmployeeStanding | null;
        /** The same number for every year; null unless the viewer may set it. */
        paidDays?: number | null;
        /** Null unless the viewer may see the Employee's Time Off. */
        timeOff?: EmployeeTimeOff | null;
        work?: {
            today: string;
            period: Period;
            entries: Paginated<ListedWorkEntry>;
            total: number;
            declinedTotal: number;
        };
        travel?: {
            today: string;
            period: Period;
            travelRequests: Paginated<ListedTravelRequest>;
            total: string;
        };
        /** Every year's, newest first. */
        performanceEvaluations?: OwnPerformanceEvaluation[];
    }>(),
    { projects: () => [] },
);

const tabLabels = computed<Record<EmployeePageTab, string>>(() => ({
    overview: 'Overview',
    assignments: props.canViewPay ? 'Assignments & Pay' : 'Assignments',
    'time-off': 'Time Off',
    work: 'Work',
    travel: 'Travel',
    performance: 'Performance',
}));

function openTab(tab: string | number): void {
    router.get(show.url(props.employee.id, { query: { tab: String(tab) } }));
}

/**
 * When they started, or start, or when they left.
 */
const since = computed(() => {
    if (!props.record || !props.standing) {
        return null;
    }

    if (props.standing.status === 'future') {
        return `Starts ${formatDate(props.record.start_date)}`;
    }

    if (props.standing.status === 'former' && props.record.leaving_date) {
        return `Left ${formatDate(props.record.leaving_date)}`;
    }

    return `Since ${formatDate(props.record.start_date)}`;
});

/** The one Report a Callout action can name, when there is just one to edit. */
const onlyReport = computed(() =>
    props.reportsNeedingAManager.length === 1 &&
    props.reportsNeedingAManager[0].can_edit
        ? props.reportsNeedingAManager[0]
        : null,
);

const recordFacts = computed(() =>
    props.record
        ? [
              { label: 'Email', value: props.record.email },
              { label: 'Department', value: props.record.department },
              { label: 'Manager', value: props.record.manager?.name ?? null },
              {
                  label: 'Start Date',
                  value: formatDate(props.record.start_date),
              },
              {
                  label: 'Leaving Date',
                  value: props.record.leaving_date
                      ? formatDate(props.record.leaving_date)
                      : null,
              },
          ]
        : [],
);

const isPhone = useMediaQuery('(max-width: 639px)');

/**
 * The Assignments' columns (rules K1, P4): the chevron opening the Pay
 * history, Project, Job Title, Dates, Capacity, then Pay in effect and the
 * next change for viewers who may see Pay, and the ⋯ menu for those who
 * manage Assignments. On a phone each row stacks: the Project over its
 * Client, Job Title and Capacity, with the Pay in effect and the buttons
 * at the right.
 */
const assignmentColumns = computed<TableColumn[]>(() => [
    ...(props.canViewPay ? [{ key: 'expand', label: '' }] : []),
    { key: 'project', label: 'Project', phone: 'primary' as const },
    { key: 'job_title', label: 'Job Title', phone: 'secondary' as const },
    { key: 'dates', label: 'Dates' },
    { key: 'capacity', label: 'Capacity', phone: 'secondary' as const },
    ...(props.canViewPay
        ? [
              {
                  key: 'pay',
                  label: 'Pay in effect',
                  align: 'end' as const,
                  phone: 'end' as const,
              },
              { key: 'next', label: 'Next change' },
          ]
        : []),
    ...(props.canManage || props.canViewPay
        ? [
              {
                  key: 'actions',
                  label: '',
                  align: 'end' as const,
                  phone: 'end' as const,
              },
          ]
        : []),
]);

/** The Assignments whose Pay history is open. */
const openPayHistories = ref<Set<number>>(new Set());

function togglePayHistory(assignment: AssignmentRecord): void {
    const opened = new Set(openPayHistories.value);

    if (!opened.delete(assignment.id)) {
        opened.add(assignment.id);
    }

    openPayHistories.value = opened;
}

function isPayHistoryOpen(assignment: AssignmentRecord): boolean {
    return props.canViewPay && openPayHistories.value.has(assignment.id);
}

/** Names the chevron's action, such as "Show Pay history on Relaunch". */
function payHistoryLabel(assignment: AssignmentRecord): string {
    return `${isPayHistoryOpen(assignment) ? 'Hide' : 'Show'} Pay history on ${assignment.project.name}`;
}

/**
 * The Pay change in effect today, or null when none has taken effect yet.
 */
function payInEffect(assignment: AssignmentRecord): PayEntryRecord | null {
    return assignment.pay?.find((entry) => entry.state === 'in_effect') ?? null;
}

/** The soonest Upcoming Pay change, the last of those listed newest first. */
function nextPayChange(assignment: AssignmentRecord): PayEntryRecord | null {
    return (
        assignment.pay?.findLast((entry) => entry.state === 'upcoming') ?? null
    );
}

/**
 * The numbers over the Assignments (rule K10): the Capacity assigned, how
 * many are active and have ended, then the Pay in effect on each active
 * one, for viewers who may see Pay, at most six numbers in all.
 */
const assignmentStatItems = computed(() => {
    const stats = props.assignmentStats;

    if (!stats) {
        return [];
    }

    return [
        { label: 'Capacity assigned', value: `${stats.capacity_assigned}%` },
        {
            label: `Active ${stats.active === 1 ? 'Assignment' : 'Assignments'}${stats.ended > 0 ? ` · ${stats.ended} Ended` : ''}`,
            value: String(stats.active),
        },
        ...(stats.pay_in_effect ?? []).slice(0, 4).map((pay) => ({
            label: pay.is_internal
                ? `${payKindNames[pay.kind]} ${payPeriods[pay.kind]}, in effect`
                : `${payKindNames[pay.kind]} on ${pay.project}`,
            value: pay.amount,
        })),
    ];
});

/** The Assignment the sheet edits; none while assigning to a Project. */
const editingAssignment = ref<AssignmentRecord | null>(null);
const assignmentFormOpen = ref(false);

function assignToAProject(): void {
    editingAssignment.value = null;
    assignmentFormOpen.value = true;
}

function editAssignment(assignment: AssignmentRecord): void {
    editingAssignment.value = assignment;
    assignmentFormOpen.value = true;
}

/** The Assignment the Delete dialog asks about. */
const deletingAssignment = ref<AssignmentRecord | null>(null);
const deleteAssignmentOpen = ref(false);

function askToDeleteAssignment(assignment: AssignmentRecord): void {
    deletingAssignment.value = assignment;
    deleteAssignmentOpen.value = true;
}

/** The Paid Days per year, from whichever the viewer gets. */
const paidDaysPerYear = computed(() =>
    typeof props.paidDays === 'number'
        ? props.paidDays
        : (props.timeOff?.paid_days ?? null),
);

const paidDaysStats = computed(() =>
    (props.timeOff?.paid_days_left ?? []).map((inYear) => ({
        label: `Paid Days left in ${inYear.year}`,
        value: String(inYear.days),
    })),
);

const timeOffColumns: TableColumn[] = [
    { key: 'dates', label: 'Dates', phone: 'primary' },
    { key: 'days', label: 'Days', phone: 'secondary' },
    { key: 'kind', label: 'Kind', phone: 'secondary' },
    { key: 'state', label: 'Status', phone: 'end' },
    { key: 'decided_by', label: 'Decided by', minor: true },
];

const performanceColumns: TableColumn[] = [
    { key: 'year', label: 'Year' },
    { key: 'goals_count', label: 'Goals', align: 'end' },
    { key: 'state', label: 'Status', align: 'end' },
];

/** The FactList slot that puts Edit beside the Paid Days per year. */
const paidDaysSlot = 'fact-Paid Days per year';

setLayoutProps({
    breadcrumbs: [
        { title: props.employee.name, href: show(props.employee.id) },
    ],
});
</script>

<template>
    <Head :title="employee.name" />

    <PageContainer width="full">
        <PageHeader :title="employee.name">
            <template #lead>
                <Avatar class="size-12 shrink-0 sm:size-14">
                    <AvatarFallback class="text-section text-foreground">{{
                        getInitials(employee.name)
                    }}</AvatarFallback>
                </Avatar>
            </template>
            <template v-if="standing" #status>
                <EmploymentStatusBadge :status="standing.status" />
            </template>
            <template #description>
                <ul
                    class="flex flex-wrap items-center gap-x-4 gap-y-1 text-meta"
                    aria-label="Key facts"
                >
                    <li
                        v-if="employee.job_title"
                        class="inline-flex items-center gap-1.5"
                    >
                        <Briefcase class="size-4" aria-hidden="true" />{{
                            employee.job_title
                        }}
                    </li>
                    <template v-if="record">
                        <li
                            v-if="record.department"
                            class="inline-flex items-center gap-1.5"
                        >
                            <Building2 class="size-4" aria-hidden="true" />{{
                                record.department
                            }}
                        </li>
                        <li class="inline-flex items-center gap-1.5">
                            <UserRound class="size-4" aria-hidden="true" />
                            <template v-if="record.manager"
                                >Manager
                                <TextLink :href="show(record.manager.id)">{{
                                    record.manager.name
                                }}</TextLink></template
                            >
                            <template v-else>No Manager</template>
                        </li>
                        <li
                            v-if="since"
                            class="inline-flex items-center gap-1.5"
                        >
                            <CalendarDays class="size-4" aria-hidden="true" />{{
                                since
                            }}
                        </li>
                        <li
                            class="inline-flex min-w-0 items-center gap-1.5 break-all"
                        >
                            <Mail
                                class="size-4 shrink-0"
                                aria-hidden="true"
                            />{{ record.email }}
                        </li>
                    </template>
                </ul>
            </template>
            <template v-if="canEdit" #actions>
                <Button variant="secondary" as-child>
                    <Link :href="edit(employee.id)"><Pencil />Edit</Link>
                </Button>
            </template>
        </PageHeader>

        <Callout
            v-if="record && reportsNeedingAManager.length > 0"
            tone="attention"
            data-test="reports-needing-a-manager"
        >
            {{ employee.name }} has left, but
            <template
                v-for="(report, position) in reportsNeedingAManager"
                :key="report.id"
                ><template v-if="position > 0">{{
                    position === reportsNeedingAManager.length - 1
                        ? ' and '
                        : ', '
                }}</template
                ><TextLink
                    :href="report.can_edit ? edit(report.id) : show(report.id)"
                    >{{ report.name }}</TextLink
                ></template
            >
            still
            {{ reportsNeedingAManager.length === 1 ? 'reports' : 'report' }}
            to them and
            {{ reportsNeedingAManager.length === 1 ? 'needs' : 'need' }} a new
            Manager.
            <template v-if="onlyReport" #action>
                <Button variant="secondary" size="sm" as-child>
                    <Link :href="edit(onlyReport.id)"
                        >Choose a new Manager for {{ onlyReport.name }}</Link
                    >
                </Button>
            </template>
        </Callout>

        <Tabs :model-value="tab" @update:model-value="openTab">
            <TabsList v-if="tabs.length > 1">
                <TabsTrigger
                    v-for="offered in tabs"
                    :key="offered"
                    :value="offered"
                    >{{ tabLabels[offered] }}</TabsTrigger
                >
            </TabsList>
            <TabsContent value="overview" class="mt-6">
                <div
                    class="grid gap-10"
                    :class="
                        record && reportingChain
                            ? 'lg:grid-cols-[minmax(0,1fr)_20rem]'
                            : ''
                    "
                >
                    <EmployeePageSection v-if="record" title="Record">
                        <FactList :facts="recordFacts">
                            <template v-if="record.manager" #fact-Manager>
                                <TextLink :href="show(record.manager.id)">{{
                                    record.manager.name
                                }}</TextLink>
                            </template>
                        </FactList>
                    </EmployeePageSection>

                    <EmployeePageSection
                        v-if="reportingChain"
                        title="Reporting Chain"
                    >
                        <ReportingChain :chain="reportingChain" />
                    </EmployeePageSection>
                </div>
            </TabsContent>
            <TabsContent value="assignments" class="mt-6 flex flex-col gap-10">
                <StatStrip
                    v-if="assignments && assignments.length > 0"
                    :stats="assignmentStatItems"
                    data-test="assignment-stats"
                />

                <EmployeePageSection v-if="assignments" title="Assignments">
                    <template v-if="canManage" #action>
                        <Button
                            class="max-sm:h-11 max-sm:w-full"
                            @click="assignToAProject"
                            ><Plus />Assign to a Project</Button
                        >
                    </template>

                    <DataTable
                        :columns="assignmentColumns"
                        :rows="assignments"
                        :has-after-row="isPayHistoryOpen"
                    >
                        <template #head-expand>
                            <span class="sr-only">Pay history</span>
                        </template>
                        <template #cell-expand="{ row }">
                            <IconButton
                                :label="payHistoryLabel(row)"
                                :aria-expanded="isPayHistoryOpen(row)"
                                @click="togglePayHistory(row)"
                                ><ChevronDown
                                    v-if="isPayHistoryOpen(row)" /><ChevronRight
                                    v-else
                            /></IconButton>
                        </template>
                        <template #cell-project="{ row }">
                            <span
                                class="flex flex-wrap items-center gap-x-2"
                                :class="
                                    row.has_ended && 'text-muted-foreground'
                                "
                                data-test="assignment"
                            >
                                <span class="font-medium">{{
                                    row.project.name
                                }}</span>
                                <StatusBadge
                                    v-if="row.has_ended"
                                    state="ended"
                                />
                            </span>
                            <span
                                class="block text-meta font-normal text-muted-foreground max-sm:hidden"
                                >{{
                                    row.project.is_internal
                                        ? 'Internal Assignment'
                                        : (row.project.client ?? 'No Client')
                                }}</span
                            >
                        </template>
                        <template #cell-job_title="{ row }">
                            <span v-if="isPhone"
                                >{{
                                    row.project.is_internal
                                        ? 'Internal'
                                        : (row.project.client ?? 'No Client')
                                }}
                                · </span
                            ><span
                                :class="
                                    row.has_ended && 'text-muted-foreground'
                                "
                                ><ValueOr :value="row.job_title"
                            /></span>
                        </template>
                        <template #cell-dates="{ row }">
                            <span
                                class="whitespace-nowrap tabular-nums"
                                :class="
                                    row.has_ended && 'text-muted-foreground'
                                "
                                >{{
                                    formatPeriod(row.start_date, row.end_date)
                                }}</span
                            >
                        </template>
                        <template #cell-capacity="{ row }">
                            <CapacityBar
                                :capacity="row.capacity"
                                :class="
                                    row.has_ended && 'text-muted-foreground'
                                "
                            />
                        </template>
                        <template #cell-pay="{ row }">
                            <span
                                v-if="payInEffect(row)"
                                class="whitespace-nowrap tabular-nums"
                                :class="
                                    row.has_ended && 'text-muted-foreground'
                                "
                                ><span class="font-semibold">{{
                                    payInEffect(row)?.amount
                                }}</span>
                                {{ payPeriods[payInEffect(row)!.kind] }}</span
                            ><span v-else class="text-muted-foreground"
                                >None in effect</span
                            >
                        </template>
                        <template #cell-next="{ row }">
                            <StatusBadge
                                v-if="nextPayChange(row)"
                                state="upcoming"
                                >{{
                                    formatDate(
                                        nextPayChange(row)!.effective_from,
                                    )
                                }}</StatusBadge
                            ><span v-else class="text-muted-foreground"
                                >None</span
                            >
                        </template>
                        <template #head-actions>
                            <span class="sr-only">Actions</span>
                        </template>
                        <template #cell-actions="{ row }">
                            <div class="flex items-center justify-end gap-1">
                                <IconButton
                                    v-if="isPhone && canViewPay"
                                    :label="payHistoryLabel(row)"
                                    :aria-expanded="isPayHistoryOpen(row)"
                                    class="size-11"
                                    @click="togglePayHistory(row)"
                                    ><ChevronDown
                                        v-if="
                                            isPayHistoryOpen(row)
                                        " /><ChevronRight v-else
                                /></IconButton>
                                <RowActions
                                    v-if="canManage"
                                    :label="`the Assignment to ${row.project.name}`"
                                >
                                    <template #menu>
                                        <DropdownMenuItem
                                            @select="editAssignment(row)"
                                            >Edit</DropdownMenuItem
                                        >
                                        <template v-if="row.can_delete">
                                            <DropdownMenuSeparator />
                                            <DropdownMenuItem
                                                variant="destructive"
                                                @select="
                                                    askToDeleteAssignment(row)
                                                "
                                                >Delete…</DropdownMenuItem
                                            >
                                        </template>
                                    </template>
                                </RowActions>
                            </div>
                        </template>
                        <template #after-row="{ row }">
                            <PayHistoryRow
                                v-if="row.pay"
                                :assignment="{ ...row, pay: row.pay }"
                                :currency="currency"
                            />
                        </template>
                        <template #empty>
                            <EmptyState
                                :icon="FolderKanban"
                                :message="`The Projects ${employee.name} is assigned to appear here. There are no Assignments yet.`"
                            />
                        </template>
                    </DataTable>
                </EmployeePageSection>

                <AssignmentForm
                    v-if="canManage"
                    v-model:open="assignmentFormOpen"
                    :employee="employee"
                    :projects="projects"
                    :assignment="editingAssignment"
                />
                <ConfirmDeleteDialog
                    v-if="deletingAssignment"
                    v-model:open="deleteAssignmentOpen"
                    without-trigger
                    :form="
                        AssignmentController.destroy.form(deletingAssignment.id)
                    "
                    :title="`Delete the Assignment to ${deletingAssignment.project.name}?`"
                    description="Its Pay is deleted with it. This can't be undone."
                    confirm-label="Delete Assignment"
                />
            </TabsContent>
            <TabsContent value="time-off" class="mt-6 flex flex-col gap-10">
                <StatStrip
                    v-if="paidDaysStats.length > 0"
                    :stats="paidDaysStats"
                    data-test="paid-days-left"
                />

                <EmployeePageSection
                    v-if="paidDaysPerYear !== null"
                    title="Paid Days"
                >
                    <FactList
                        :facts="[
                            {
                                label: 'Paid Days per year',
                                value: String(paidDaysPerYear),
                            },
                        ]"
                    >
                        <template #[paidDaysSlot]>
                            <span class="inline-flex items-center gap-3">
                                <span class="tabular-nums">{{
                                    formatDays(paidDaysPerYear)
                                }}</span>
                                <PaidDaysDialog
                                    v-if="typeof paidDays === 'number'"
                                    :employee="employee"
                                    :paid-days="paidDays"
                                />
                            </span>
                        </template>
                    </FactList>
                </EmployeePageSection>

                <EmployeePageSection v-if="timeOff" title="Time Off">
                    <DataTable
                        :columns="timeOffColumns"
                        :rows="timeOff.requests"
                    >
                        <template #cell-dates="{ row }">
                            <span class="tabular-nums" data-test="time-off">{{
                                formatDateRange(row.first_date, row.last_date)
                            }}</span>
                            <span
                                v-if="row.decline_reason"
                                class="block text-caption font-normal text-status-declined-foreground"
                                >Declined<template v-if="row.decided_by">
                                    by {{ row.decided_by }}</template
                                >: {{ row.decline_reason }}</span
                            >
                        </template>
                        <template #cell-days="{ row }">
                            <span class="whitespace-nowrap tabular-nums">{{
                                formatDays(row.days)
                            }}</span>
                        </template>
                        <template #cell-kind="{ row }">
                            {{ row.is_paid ? 'Paid' : 'Unpaid' }}
                        </template>
                        <template #cell-state="{ row }">
                            <StatusBadge :state="row.state" />
                        </template>
                        <template #cell-decided_by="{ row }">
                            <PersonCell
                                v-if="row.decided_by"
                                :name="row.decided_by"
                            />
                        </template>
                        <template #empty>
                            <EmptyState
                                :icon="CalendarDays"
                                :message="`${employee.name}'s Time Off requests appear here. They haven't requested any yet.`"
                            />
                        </template>
                    </DataTable>
                </EmployeePageSection>
            </TabsContent>
            <TabsContent value="work" class="mt-6">
                <EmployeeWorkTab
                    v-if="work"
                    :employee="employee"
                    :work="work"
                />
            </TabsContent>
            <TabsContent value="travel" class="mt-6">
                <EmployeeTravelTab
                    v-if="travel"
                    :employee="employee"
                    :travel="travel"
                />
            </TabsContent>
            <TabsContent value="performance" class="mt-6">
                <EmployeePageSection
                    v-if="performanceEvaluations"
                    title="Performance Evaluations"
                >
                    <DataTable
                        :columns="performanceColumns"
                        :rows="performanceEvaluations"
                    >
                        <template #cell-year="{ row }">
                            <Link
                                :href="performanceEvaluationPage(row.id)"
                                class="font-medium underline-offset-4 hover:underline"
                                data-test="performance-evaluation"
                                >{{ row.year }}</Link
                            >
                        </template>
                        <template #cell-goals_count="{ row }">
                            <span class="tabular-nums">{{
                                row.goals_count
                            }}</span>
                        </template>
                        <template #cell-state="{ row }">
                            <div
                                class="flex items-center justify-end gap-2 whitespace-nowrap"
                            >
                                <span
                                    v-if="row.outcome"
                                    class="text-muted-foreground"
                                    >{{ outcomes[row.outcome] }}</span
                                >
                                <StatusBadge :state="row.state" />
                                <StatusBadge
                                    v-if="row.at_risk_goals_count > 0"
                                    state="at-risk"
                                    >{{ row.at_risk_goals_count }} At
                                    Risk</StatusBadge
                                >
                            </div>
                        </template>
                        <template #empty>
                            <EmptyState
                                :icon="Target"
                                :message="`${employee.name}'s Performance Evaluations appear here. There are none yet.`"
                            />
                        </template>
                    </DataTable>
                </EmployeePageSection>
            </TabsContent>
        </Tabs>
    </PageContainer>
</template>

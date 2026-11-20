<script setup lang="ts">
import { Head, Link, router, setLayoutProps } from '@inertiajs/vue3';
import {
    Briefcase,
    Building2,
    CalendarDays,
    FolderKanban,
    Mail,
    Pencil,
    Target,
    UserRound,
} from '@lucide/vue';
import { computed } from 'vue';
import AssignmentController from '@/actions/App/Http/Controllers/Assignments/AssignmentController';
import AssignmentForm from '@/components/assignments/AssignmentForm.vue';
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
import PageContainer from '@/components/PageContainer.vue';
import PageHeader from '@/components/PageHeader.vue';
import PayDialog from '@/components/pay/PayDialog.vue';
import PersonCell from '@/components/PersonCell.vue';
import StatStrip from '@/components/StatStrip.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import TextLink from '@/components/TextLink.vue';
import PaidDaysDialog from '@/components/time-off/PaidDaysDialog.vue';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import ValueOr from '@/components/ValueOr.vue';
import { getInitials } from '@/composables/useInitials';
import { formatDate, formatDateRange, formatPeriod } from '@/lib/dates';
import { outcomes } from '@/lib/outcomes';
import { formatPay } from '@/lib/pay';
import { formatDays } from '@/lib/time-off';
import { edit, show } from '@/routes/employees';
import { show as performanceEvaluationPage } from '@/routes/performance-evaluations';
import type {
    AssignmentRecord,
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
        assignments?: AssignmentRecord[];
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

const hasActions = computed(() => props.canManage || props.canManagePay);

/**
 * The Assignments' columns from `md` up: Project, Job Title, Dates,
 * Capacity, then Pay and the buttons when the viewer gets them. Each string
 * is written out in full so Tailwind finds it.
 */
const assignmentGrid = computed(() => {
    if (props.canViewPay && hasActions.value) {
        return 'md:grid-cols-[minmax(0,1.3fr)_minmax(0,1fr)_minmax(0,1.3fr)_5rem_minmax(0,1.1fr)_11rem]';
    }

    if (props.canViewPay) {
        return 'md:grid-cols-[minmax(0,1.3fr)_minmax(0,1fr)_minmax(0,1.3fr)_5rem_minmax(0,1.1fr)]';
    }

    if (props.canManage) {
        return 'md:grid-cols-[minmax(0,1.3fr)_minmax(0,1fr)_minmax(0,1.3fr)_5rem_11rem]';
    }

    return 'md:grid-cols-[minmax(0,1.3fr)_minmax(0,1fr)_minmax(0,1.3fr)_5rem]';
});

/** Each Assignment with the Pay in effect today, found once per render. */
const assignmentRows = computed(() =>
    (props.assignments ?? []).map((assignment) => ({
        assignment,
        pay: payInEffect(assignment),
    })),
);

/**
 * The entry in effect today, or null when none has taken effect yet.
 */
function payInEffect(assignment: AssignmentRecord): PayEntryRecord | null {
    return assignment.pay?.find((entry) => entry.is_in_effect) ?? null;
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
                <EmployeePageSection v-if="assignments" title="Assignments">
                    <template v-if="canManage" #action>
                        <AssignmentForm
                            :employee="employee"
                            :projects="projects"
                        />
                    </template>

                    <div class="overflow-hidden rounded-lg border text-sm">
                        <div
                            v-if="assignments.length > 0"
                            :class="assignmentGrid"
                            class="hidden gap-x-4 bg-muted/50 px-4 py-2 font-medium text-muted-foreground md:grid"
                            aria-hidden="true"
                        >
                            <span>Project</span>
                            <span>Job Title</span>
                            <span>Dates</span>
                            <span class="text-right">Capacity</span>
                            <span v-if="canViewPay">Pay</span>
                            <span v-if="hasActions" />
                        </div>
                        <ul
                            v-if="assignments.length > 0"
                            class="divide-y md:border-t"
                        >
                            <li
                                v-for="{ assignment, pay } in assignmentRows"
                                :key="assignment.id"
                                :class="[
                                    assignmentGrid,
                                    assignment.has_ended &&
                                        'text-muted-foreground',
                                ]"
                                class="grid grid-cols-[minmax(0,1fr)_auto] items-start gap-x-4 gap-y-1 px-4 py-3 md:items-center"
                                data-test="assignment"
                            >
                                <div
                                    class="col-start-1 row-start-1 min-w-0 md:col-start-auto md:row-start-auto"
                                >
                                    <p
                                        class="flex flex-wrap items-center gap-2 font-medium"
                                        :class="
                                            !assignment.has_ended &&
                                            'text-foreground'
                                        "
                                    >
                                        {{ assignment.project.name }}
                                        <Badge
                                            v-if="assignment.has_ended"
                                            variant="secondary"
                                            >Ended</Badge
                                        >
                                    </p>
                                    <p class="text-muted-foreground">
                                        {{
                                            assignment.project.is_internal
                                                ? 'Internal'
                                                : (assignment.project.client ??
                                                  'No Client')
                                        }}
                                    </p>
                                </div>
                                <p
                                    class="order-1 col-span-2 text-muted-foreground md:order-none md:col-span-1"
                                >
                                    <span class="sr-only">Job Title: </span
                                    ><ValueOr :value="assignment.job_title" />
                                </p>
                                <p
                                    class="order-1 col-span-2 text-muted-foreground md:order-none md:col-span-1"
                                >
                                    <span class="sr-only">Dates: </span
                                    >{{
                                        formatPeriod(
                                            assignment.start_date,
                                            assignment.end_date,
                                        )
                                    }}<span class="md:hidden">
                                        · {{ assignment.capacity }}%
                                        capacity</span
                                    >
                                </p>
                                <p
                                    class="hidden text-right text-muted-foreground tabular-nums md:block"
                                >
                                    <span class="sr-only">Capacity: </span
                                    >{{ assignment.capacity }}%
                                </p>
                                <p
                                    v-if="canViewPay"
                                    class="order-2 col-span-2 md:order-none md:col-span-1"
                                >
                                    <span
                                        class="text-muted-foreground md:sr-only"
                                        >Pay: </span
                                    ><span
                                        v-if="pay"
                                        class="font-medium"
                                        :class="
                                            !assignment.has_ended &&
                                            'text-foreground'
                                        "
                                        >{{ formatPay(pay) }}</span
                                    ><span v-else class="text-muted-foreground"
                                        >None in effect</span
                                    >
                                </p>
                                <div
                                    v-if="hasActions"
                                    class="col-start-2 row-start-1 flex justify-end gap-1 md:col-start-auto md:row-start-auto"
                                >
                                    <PayDialog
                                        v-if="assignment.pay && currency"
                                        :assignment="{
                                            ...assignment,
                                            pay: assignment.pay,
                                        }"
                                        :currency="currency"
                                    />
                                    <AssignmentForm
                                        v-if="canManage"
                                        :employee="employee"
                                        :projects="projects"
                                        :assignment="assignment"
                                    />
                                    <ConfirmDeleteDialog
                                        v-if="assignment.can_delete"
                                        :form="
                                            AssignmentController.destroy.form(
                                                assignment.id,
                                            )
                                        "
                                        :title="`Delete the Assignment to ${assignment.project.name}?`"
                                        description="Its Pay is deleted with it. This can't be undone."
                                    >
                                        <template #trigger>
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                class="text-destructive"
                                                :aria-label="`Delete the Assignment to ${assignment.project.name}`"
                                                >Delete</Button
                                            >
                                        </template>
                                    </ConfirmDeleteDialog>
                                </div>
                            </li>
                        </ul>
                        <EmptyState
                            v-else
                            :icon="FolderKanban"
                            :message="`The Projects ${employee.name} is assigned to appear here. There are no Assignments yet.`"
                        >
                            <template v-if="canManage" #action>
                                <AssignmentForm
                                    :employee="employee"
                                    :projects="projects"
                                />
                            </template>
                        </EmptyState>
                    </div>
                </EmployeePageSection>
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

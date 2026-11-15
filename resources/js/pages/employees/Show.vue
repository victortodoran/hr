<script setup lang="ts">
import { Head, Link, router, setLayoutProps } from '@inertiajs/vue3';
import { CalendarDays, FolderKanban, TriangleAlert } from '@lucide/vue';
import { computed } from 'vue';
import AssignmentForm from '@/components/assignments/AssignmentForm.vue';
import DataTable from '@/components/DataTable.vue';
import EmploymentStatusBadge from '@/components/employees/EmploymentStatusBadge.vue';
import EmptyState from '@/components/EmptyState.vue';
import EmployeePageSection from '@/components/employees/EmployeePageSection.vue';
import PageContainer from '@/components/PageContainer.vue';
import PayDialog from '@/components/pay/PayDialog.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import PaidDaysForm from '@/components/time-off/PaidDaysForm.vue';
import TimeOffSummary from '@/components/time-off/TimeOffSummary.vue';
import ReportingChain from '@/components/employees/ReportingChain.vue';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import ValueOr from '@/components/ValueOr.vue';
import { formatDate, formatPeriod } from '@/lib/dates';
import { useInitials } from '@/composables/useInitials';
import { formatPay } from '@/lib/pay';
import { edit, show } from '@/routes/employees';
import type {
    AssignmentRecord,
    Choice,
    EmployeePageRecord,
    EmployeePageTab,
    EmployeeStanding,
    EmployeeTimeOff,
    OpenProject,
    PayEntryRecord,
    ReportingChainView,
    TableColumn,
} from '@/types';

/**
 * Only the open tab's props are sent: `record`, `reportsNeedingAManager` and
 * `reportingChain` on Overview, `assignments` and `projects` on Assignments &
 * Pay, `paidDays` and `timeOff` on Time Off.
 */
const props = withDefaults(
    defineProps<{
        employee: Choice & { job_title: string | null };
        tab: EmployeePageTab;
        /** The tabs the viewer may open, Overview always first. */
        tabs: EmployeePageTab[];
        /** Null when the viewer may not see the Employee's record. */
        record?: EmployeePageRecord | null;
        reportsNeedingAManager?: Choice[];
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
    }>(),
    { reportsNeedingAManager: () => [], projects: () => [] },
);

const { getInitials } = useInitials();

/** The Record's fields, in groups: how to reach them, where they sit, and when they work. */
const recordGroups = ['Contact', 'Organisation', 'Employment'] as const;

/** A label beside its value from `sm` up, above it on a phone. */
const rowClass = 'grid gap-0.5 sm:grid-cols-[8rem_minmax(0,1fr)] sm:gap-4';

/** A link inside text: underlined, so it reads as one without hovering. */
const linkClass =
    'rounded-sm underline decoration-current/40 underline-offset-4 hover:decoration-current focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none';

const tabLabels = computed<Record<EmployeePageTab, string>>(() => ({
    overview: 'Overview',
    assignments: props.canViewPay ? 'Assignments & Pay' : 'Assignments',
    'time-off': 'Time Off',
}));

function openTab(tab: string | number): void {
    router.get(show.url(props.employee.id, { query: { tab: String(tab) } }));
}

const assignmentColumns = computed<TableColumn[]>(() => [
    { key: 'project', label: 'Project' },
    { key: 'job_title', label: 'Job Title', minor: true },
    { key: 'dates', label: 'Dates', minor: true },
    { key: 'capacity', label: 'Capacity', align: 'end', minor: true },
    ...(props.canViewPay ? [{ key: 'pay', label: 'Pay', minor: true }] : []),
    ...(props.canManage || props.canViewPay
        ? [{ key: 'actions', label: 'Actions', align: 'end' as const }]
        : []),
]);

const timeOffColumns: TableColumn[] = [
    { key: 'time_off', label: 'Time Off' },
    { key: 'state', label: 'State', align: 'end' },
];

/**
 * The entry in effect today, or null when none has taken effect yet.
 */
function payInEffect(assignment: AssignmentRecord): PayEntryRecord | null {
    return assignment.pay?.find((entry) => entry.is_in_effect) ?? null;
}

setLayoutProps({
    breadcrumbs: [
        { title: props.employee.name, href: show(props.employee.id) },
    ],
});
</script>

<template>
    <Head :title="employee.name" />

    <PageContainer width="wide">
        <header class="flex flex-wrap items-center gap-4">
            <Avatar class="size-14 rounded-full" aria-hidden="true">
                <AvatarFallback class="rounded-full text-lg font-medium">{{
                    getInitials(employee.name)
                }}</AvatarFallback>
            </Avatar>
            <div class="flex min-w-0 flex-1 flex-col gap-1">
                <h1 class="text-2xl font-semibold tracking-tight">
                    {{ employee.name }}
                </h1>
                <div
                    class="flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-muted-foreground"
                >
                    <span>{{ employee.job_title ?? 'Employee' }}</span>
                    <template v-if="standing">
                        <EmploymentStatusBadge :status="standing.status" />
                        <Badge v-if="standing.is_top" variant="secondary"
                            >Top Employee</Badge
                        >
                    </template>
                </div>
            </div>
            <Button v-if="canEdit" variant="outline" as-child>
                <Link :href="edit(employee.id)">Edit</Link>
            </Button>
        </header>

        <Tabs :model-value="tab" @update:model-value="openTab">
            <TabsList
                v-if="tabs.length > 1"
                class="max-w-full justify-start self-start overflow-x-auto"
            >
                <TabsTrigger
                    v-for="offered in tabs"
                    :key="offered"
                    :value="offered"
                    >{{ tabLabels[offered] }}</TabsTrigger
                >
            </TabsList>
            <TabsContent value="overview" class="mt-6 flex flex-col gap-10">
                <div
                    v-if="record && reportsNeedingAManager.length > 0"
                    class="flex gap-3 rounded-lg bg-status-pending p-4 text-sm text-status-pending-foreground"
                >
                    <TriangleAlert
                        class="mt-0.5 size-4 shrink-0"
                        aria-hidden="true"
                    />
                    <div class="grid gap-1">
                        <p class="font-medium">
                            {{ employee.name }} has left, but these Employees
                            still report to them and need a new Manager:
                        </p>
                        <ul class="flex flex-wrap gap-x-4 gap-y-1">
                            <li
                                v-for="report in reportsNeedingAManager"
                                :key="report.id"
                            >
                                <Link
                                    :href="show(report.id)"
                                    :class="linkClass"
                                    >{{ report.name }}</Link
                                >
                            </li>
                        </ul>
                    </div>
                </div>

                <div
                    class="grid items-start gap-10 lg:gap-12"
                    :class="record && reportingChain && 'lg:grid-cols-2'"
                >
                    <EmployeePageSection v-if="record" title="Record">
                        <div class="divide-y rounded-lg border text-sm">
                            <div
                                v-for="group in recordGroups"
                                :key="group"
                                class="grid gap-3 p-4"
                            >
                                <h3
                                    class="text-xs font-medium text-muted-foreground"
                                >
                                    {{ group }}
                                </h3>
                                <dl class="grid gap-3">
                                    <template v-if="group === 'Contact'">
                                        <div :class="rowClass">
                                            <dt class="text-muted-foreground">
                                                Email
                                            </dt>
                                            <dd class="break-all">
                                                {{ record.email }}
                                            </dd>
                                        </div>
                                    </template>
                                    <template
                                        v-else-if="group === 'Organisation'"
                                    >
                                        <div :class="rowClass">
                                            <dt class="text-muted-foreground">
                                                Department
                                            </dt>
                                            <dd>
                                                <ValueOr
                                                    :value="record.department"
                                                />
                                            </dd>
                                        </div>
                                        <div :class="rowClass">
                                            <dt class="text-muted-foreground">
                                                Manager
                                            </dt>
                                            <dd>
                                                <Link
                                                    v-if="record.manager"
                                                    :href="
                                                        show(record.manager.id)
                                                    "
                                                    :class="linkClass"
                                                    >{{
                                                        record.manager.name
                                                    }}</Link
                                                >
                                                <span
                                                    v-else
                                                    class="text-muted-foreground"
                                                    >None (top Employee)</span
                                                >
                                            </dd>
                                        </div>
                                    </template>
                                    <template v-else>
                                        <div :class="rowClass">
                                            <dt class="text-muted-foreground">
                                                Start Date
                                            </dt>
                                            <dd>
                                                {{
                                                    formatDate(
                                                        record.start_date,
                                                    )
                                                }}
                                            </dd>
                                        </div>
                                        <div :class="rowClass">
                                            <dt class="text-muted-foreground">
                                                Leaving Date
                                            </dt>
                                            <dd>
                                                <ValueOr
                                                    :value="
                                                        record.leaving_date
                                                            ? formatDate(
                                                                  record.leaving_date,
                                                              )
                                                            : null
                                                    "
                                                    fallback="Not set"
                                                />
                                            </dd>
                                        </div>
                                    </template>
                                </dl>
                            </div>
                        </div>
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

                    <DataTable :columns="assignmentColumns" :rows="assignments">
                        <template #cell-project="{ row }">
                            <p class="font-medium">{{ row.project.name }}</p>
                            <p class="text-muted-foreground">
                                {{
                                    row.project.is_internal
                                        ? 'Internal'
                                        : (row.project.client ?? 'No Client')
                                }}
                            </p>
                        </template>
                        <template #cell-job_title="{ row }">
                            <ValueOr :value="row.job_title" />
                        </template>
                        <template #cell-dates="{ row }">
                            {{ formatPeriod(row.start_date, row.end_date) }}
                        </template>
                        <template #cell-capacity="{ row }">
                            {{ row.capacity }}%
                        </template>
                        <template #cell-pay="{ row }">
                            {{
                                payInEffect(row)
                                    ? formatPay(payInEffect(row)!)
                                    : 'None in effect'
                            }}
                        </template>
                        <template #head-actions>
                            <span class="sr-only">Actions</span>
                        </template>
                        <template #cell-actions="{ row }">
                            <div class="flex justify-end gap-2">
                                <PayDialog
                                    v-if="row.pay"
                                    :assignment="{ ...row, pay: row.pay }"
                                    :currency="currency"
                                />
                                <AssignmentForm
                                    v-if="canManage"
                                    :employee="employee"
                                    :projects="projects"
                                    :assignment="row"
                                />
                            </div>
                        </template>
                        <template #empty>
                            <EmptyState
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
                        </template>
                    </DataTable>
                </EmployeePageSection>
            </TabsContent>
            <TabsContent value="time-off" class="mt-6 flex flex-col gap-10">
                <EmployeePageSection
                    v-if="paidDays !== null && paidDays !== undefined"
                    title="Paid Days"
                    description="Days of Paid Time Off every year gets. Changing them changes past years too."
                >
                    <div class="rounded-lg border p-4">
                        <PaidDaysForm
                            :employee="employee"
                            :paid-days="paidDays"
                        />
                    </div>
                </EmployeePageSection>

                <EmployeePageSection v-if="timeOff" title="Time Off">
                    <div>
                        <p class="text-sm">
                            <span
                                class="font-semibold tabular-nums"
                                :class="{
                                    'text-destructive':
                                        timeOff.paid_days_left < 0,
                                }"
                                >{{ timeOff.paid_days_left }}</span
                            >
                            <span class="text-muted-foreground">
                                of {{ timeOff.paid_days }} Paid Days left in
                                {{ timeOff.year }}</span
                            >
                        </p>
                    </div>
                    <DataTable
                        :columns="timeOffColumns"
                        :rows="timeOff.requests"
                    >
                        <template #cell-time_off="{ row }">
                            <TimeOffSummary :time-off="row" />
                        </template>
                        <template #cell-state="{ row }">
                            <StatusBadge :state="row.state" />
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
        </Tabs>
    </PageContainer>
</template>

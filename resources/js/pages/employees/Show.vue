<script setup lang="ts">
import { Head, Link, router, setLayoutProps } from '@inertiajs/vue3';
import { CalendarDays, FolderKanban } from '@lucide/vue';
import { computed } from 'vue';
import AssignmentForm from '@/components/assignments/AssignmentForm.vue';
import DataTable from '@/components/DataTable.vue';
import EmploymentStatusBadge from '@/components/employees/EmploymentStatusBadge.vue';
import EmptyState from '@/components/EmptyState.vue';
import Heading from '@/components/Heading.vue';
import PageContainer from '@/components/PageContainer.vue';
import PayDialog from '@/components/pay/PayDialog.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import PaidDaysForm from '@/components/time-off/PaidDaysForm.vue';
import TimeOffSummary from '@/components/time-off/TimeOffSummary.vue';
import ReportingChain from '@/components/employees/ReportingChain.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import ValueOr from '@/components/ValueOr.vue';
import { formatDate, formatPeriod } from '@/lib/dates';
import { formatPay } from '@/lib/pay';
import { edit, show } from '@/routes/employees';
import type {
    AssignmentRecord,
    Choice,
    EmployeePageRecord,
    EmployeePageTab,
    EmployeeTimeOff,
    OpenProject,
    PaidDaysRecord,
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
        /** This year's and next year's; null unless the viewer may set them. */
        paidDays?: PaidDaysRecord[] | null;
        /** Null unless the viewer may see the Employee's Time Off. */
        timeOff?: EmployeeTimeOff | null;
    }>(),
    { reportsNeedingAManager: () => [], projects: () => [] },
);

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
        <div class="flex items-start justify-between gap-4">
            <Heading
                :title="employee.name"
                :description="employee.job_title ?? 'Employee'"
            />
            <div class="flex items-center gap-2">
                <template v-if="record">
                    <EmploymentStatusBadge :status="record.status" />
                    <Badge v-if="record.is_top" variant="secondary"
                        >Top Employee</Badge
                    >
                </template>
                <Button v-if="canEdit" variant="outline" as-child>
                    <Link :href="edit(employee.id)">Edit</Link>
                </Button>
            </div>
        </div>

        <Tabs :model-value="tab" @update:model-value="openTab">
            <TabsList v-if="tabs.length > 1" class="self-start">
                <TabsTrigger
                    v-for="offered in tabs"
                    :key="offered"
                    :value="offered"
                    >{{ tabLabels[offered] }}</TabsTrigger
                >
            </TabsList>
            <TabsContent value="overview" class="mt-4 flex flex-col gap-8">
                <section v-if="record" class="flex flex-col gap-4">
                    <h2 class="text-lg font-semibold">Record</h2>
                    <dl
                        class="grid gap-x-8 gap-y-3 rounded-lg border p-4 text-sm sm:grid-cols-2"
                    >
                        <div>
                            <dt class="text-muted-foreground">Email</dt>
                            <dd>{{ record.email }}</dd>
                        </div>
                        <div>
                            <dt class="text-muted-foreground">Department</dt>
                            <dd><ValueOr :value="record.department" /></dd>
                        </div>
                        <div>
                            <dt class="text-muted-foreground">Manager</dt>
                            <dd>
                                <Link
                                    v-if="record.manager"
                                    :href="show(record.manager.id)"
                                    class="underline-offset-4 hover:underline"
                                    >{{ record.manager.name }}</Link
                                >
                                <span v-else class="text-muted-foreground"
                                    >None (top Employee)</span
                                >
                            </dd>
                        </div>
                        <div>
                            <dt class="text-muted-foreground">Start Date</dt>
                            <dd>{{ formatDate(record.start_date) }}</dd>
                        </div>
                        <div>
                            <dt class="text-muted-foreground">Leaving Date</dt>
                            <dd>
                                <ValueOr
                                    :value="
                                        record.leaving_date
                                            ? formatDate(record.leaving_date)
                                            : null
                                    "
                                    fallback="Not set"
                                />
                            </dd>
                        </div>
                    </dl>

                    <div
                        v-if="reportsNeedingAManager.length > 0"
                        class="grid gap-2 rounded-lg border border-amber-300 p-4 text-sm dark:border-amber-500/40"
                    >
                        <p class="font-medium">
                            {{ employee.name }} has left, but these Employees
                            still report to them and need a new Manager:
                        </p>
                        <ul class="list-inside list-disc">
                            <li
                                v-for="report in reportsNeedingAManager"
                                :key="report.id"
                            >
                                <Link
                                    :href="show(report.id)"
                                    class="underline"
                                    >{{ report.name }}</Link
                                >
                            </li>
                        </ul>
                    </div>
                </section>

                <section
                    v-if="reportingChain"
                    class="flex max-w-xl flex-col gap-4"
                >
                    <h2 class="text-lg font-semibold">Reporting Chain</h2>
                    <ReportingChain :chain="reportingChain" />
                </section>
            </TabsContent>
            <TabsContent value="assignments" class="mt-4">
                <section v-if="assignments" class="flex flex-col gap-4">
                    <div class="flex items-start justify-between gap-4">
                        <h2 class="text-lg font-semibold">Assignments</h2>
                        <AssignmentForm
                            v-if="canManage"
                            :employee="employee"
                            :projects="projects"
                        />
                    </div>

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
                </section>
            </TabsContent>
            <TabsContent value="time-off" class="mt-4 flex flex-col gap-8">
                <section v-if="paidDays" class="flex flex-col gap-4">
                    <div>
                        <h2 class="text-lg font-semibold">Paid Days</h2>
                        <p class="text-sm text-muted-foreground">
                            Days of Paid Time Off each year. A year with no
                            number of its own keeps the year before's.
                        </p>
                    </div>
                    <div class="flex flex-wrap gap-8 rounded-lg border p-4">
                        <PaidDaysForm
                            v-for="year in paidDays"
                            :key="year.year"
                            :employee="employee"
                            :paid-days="year"
                        />
                    </div>
                </section>

                <section v-if="timeOff" class="flex flex-col gap-4">
                    <div>
                        <h2 class="text-lg font-semibold">Time Off</h2>
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
                </section>
            </TabsContent>
        </Tabs>
    </PageContainer>
</template>

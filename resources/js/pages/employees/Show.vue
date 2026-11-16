<script setup lang="ts">
import { Head, Link, router, setLayoutProps } from '@inertiajs/vue3';
import { CalendarDays, FolderKanban, TriangleAlert } from '@lucide/vue';
import { computed } from 'vue';
import AssignmentForm from '@/components/assignments/AssignmentForm.vue';
import EmploymentStatusBadge from '@/components/employees/EmploymentStatusBadge.vue';
import EmptyState from '@/components/EmptyState.vue';
import EmployeePageSection from '@/components/employees/EmployeePageSection.vue';
import PageContainer from '@/components/PageContainer.vue';
import PayDialog from '@/components/pay/PayDialog.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import PaidDaysForm from '@/components/time-off/PaidDaysForm.vue';
import TimeOffSummary from '@/components/time-off/TimeOffSummary.vue';
import TravelRequestList from '@/components/travel-requests/TravelRequestList.vue';
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
    TravelRequestRecord,
} from '@/types';

/**
 * Only the open tab's props are sent: `record`, `reportsNeedingAManager` and
 * `reportingChain` on Overview, `assignments` and `projects` on Assignments &
 * Pay, `paidDays` and `timeOff` on Time Off, `travelRequests` on Travel.
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
        /** Only to be seen: nothing on them can be done from here. */
        travelRequests?: TravelRequestRecord[];
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
    travel: 'Travel',
}));

function openTab(tab: string | number): void {
    router.get(show.url(props.employee.id, { query: { tab: String(tab) } }));
}

const hasActions = computed(() => props.canManage || props.canViewPay);

/**
 * The Assignments' columns from `md` up: Project, Job Title, Dates,
 * Capacity, then Pay and the buttons when the viewer gets them. Each string
 * is written out in full so Tailwind finds it.
 */
const assignmentGrid = computed(() => {
    if (props.canViewPay) {
        return 'md:grid-cols-[minmax(0,1.3fr)_minmax(0,1fr)_minmax(0,1.3fr)_5rem_minmax(0,1.1fr)_7.5rem]';
    }

    if (props.canManage) {
        return 'md:grid-cols-[minmax(0,1.3fr)_minmax(0,1fr)_minmax(0,1.3fr)_5rem_7.5rem]';
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

                <div class="flex flex-col gap-10">
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
                                                    >None</span
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
                        class="max-w-xl"
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
                                        v-if="assignment.pay"
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
                <EmployeePageSection
                    v-if="
                        timeOff || (paidDays !== null && paidDays !== undefined)
                    "
                    title="Paid Days"
                >
                    <p v-if="timeOff" data-test="paid-days-left">
                        <span
                            class="text-2xl font-semibold tabular-nums"
                            :class="
                                timeOff.paid_days_left < 0 && 'text-destructive'
                            "
                            >{{ timeOff.paid_days_left }}</span
                        >
                        <span class="ml-1.5 text-sm text-muted-foreground"
                            >of {{ timeOff.paid_days }} Paid Days left in
                            {{ timeOff.year }}</span
                        >
                    </p>
                    <div
                        v-if="typeof paidDays === 'number'"
                        class="flex flex-col gap-4 rounded-lg border p-4 sm:flex-row sm:items-end sm:justify-between"
                    >
                        <p class="max-w-sm text-sm text-muted-foreground">
                            Days of Paid Time Off {{ employee.name }} gets each
                            year. Changing this also changes past years.
                        </p>
                        <PaidDaysForm
                            :employee="employee"
                            :paid-days="paidDays"
                        />
                    </div>
                </EmployeePageSection>

                <EmployeePageSection v-if="timeOff" title="Time Off">
                    <div class="overflow-hidden rounded-lg border text-sm">
                        <ul v-if="timeOff.requests.length > 0" class="divide-y">
                            <li
                                v-for="request in timeOff.requests"
                                :key="request.id"
                                class="flex items-start justify-between gap-4 px-4 py-3"
                                data-test="time-off"
                            >
                                <TimeOffSummary :time-off="request" />
                                <StatusBadge
                                    :state="request.state"
                                    class="mt-0.5"
                                />
                            </li>
                        </ul>
                        <EmptyState
                            v-else
                            :icon="CalendarDays"
                            :message="`${employee.name}'s Time Off requests appear here. They haven't requested any yet.`"
                        />
                    </div>
                </EmployeePageSection>
            </TabsContent>
            <TabsContent value="travel" class="mt-6 flex flex-col gap-10">
                <EmployeePageSection v-if="travelRequests" title="Travel">
                    <TravelRequestList
                        :travel-requests="travelRequests"
                        :empty-message="`${employee.name}'s Travel Requests appear here. They haven't made any yet.`"
                    />
                </EmployeePageSection>
            </TabsContent>
        </Tabs>
    </PageContainer>
</template>

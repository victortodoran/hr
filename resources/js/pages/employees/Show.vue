<script setup lang="ts">
import { Head, Link, setLayoutProps } from '@inertiajs/vue3';
import AssignmentForm from '@/components/assignments/AssignmentForm.vue';
import EmploymentStatusBadge from '@/components/employees/EmploymentStatusBadge.vue';
import Heading from '@/components/Heading.vue';
import PayDialog from '@/components/pay/PayDialog.vue';
import PaidDaysForm from '@/components/time-off/PaidDaysForm.vue';
import ReportingChain from '@/components/employees/ReportingChain.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import ValueOr from '@/components/ValueOr.vue';
import { formatDate, formatPeriod } from '@/lib/dates';
import { formatPay } from '@/lib/pay';
import { edit, show } from '@/routes/employees';
import type {
    AssignmentRecord,
    Choice,
    EmployeePageRecord,
    OpenProject,
    PaidDaysRecord,
    PayEntryRecord,
    ReportingChainView,
} from '@/types';

const props = defineProps<{
    employee: Choice & { job_title: string | null };
    /** Only sent to viewers who may see the Employee's record. */
    record: EmployeePageRecord | null;
    reportsNeedingAManager: Choice[];
    /** Null for a Former Employee, who is no longer in the reporting line. */
    reportingChain: ReportingChainView | null;
    /** Only sent to viewers who may see the Employee's Assignments. */
    assignments: AssignmentRecord[] | null;
    canManage: boolean;
    projects: OpenProject[];
    canViewPay: boolean;
    canManagePay: boolean;
    /** The company currency; only sent to those who may manage Pay. */
    currency?: string;
    canEdit: boolean;
    /** This year's and next year's; only sent to those who may manage them. */
    paidDays: PaidDaysRecord[] | null;
}>();

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

    <div class="flex max-w-4xl flex-col gap-8 p-4">
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
                    {{ employee.name }} has left, but these Employees still
                    report to them and need a new Manager:
                </p>
                <ul class="list-inside list-disc">
                    <li
                        v-for="report in reportsNeedingAManager"
                        :key="report.id"
                    >
                        <Link :href="show(report.id)" class="underline">{{
                            report.name
                        }}</Link>
                    </li>
                </ul>
            </div>
        </section>

        <section v-if="paidDays" class="flex flex-col gap-4">
            <div>
                <h2 class="text-lg font-semibold">Paid Days</h2>
                <p class="text-sm text-muted-foreground">
                    Days of Paid Time Off each year. A year with no number of
                    its own keeps the year before's.
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

        <section v-if="reportingChain" class="flex max-w-xl flex-col gap-4">
            <h2 class="text-lg font-semibold">Reporting Chain</h2>
            <ReportingChain :chain="reportingChain" />
        </section>

        <section v-if="assignments" class="flex flex-col gap-4">
            <div class="flex items-start justify-between gap-4">
                <h2 class="text-lg font-semibold">Assignments</h2>
                <AssignmentForm
                    v-if="canManage"
                    :employee="employee"
                    :projects="projects"
                />
            </div>

            <div class="overflow-x-auto rounded-lg border">
                <table class="w-full text-sm">
                    <thead class="bg-muted/50 text-left text-muted-foreground">
                        <tr>
                            <th class="px-4 py-2 font-medium">Project</th>
                            <th class="px-4 py-2 font-medium">Job Title</th>
                            <th class="px-4 py-2 font-medium">Dates</th>
                            <th class="px-4 py-2 text-right font-medium">
                                Capacity
                            </th>
                            <th v-if="canViewPay" class="px-4 py-2 font-medium">
                                Pay
                            </th>
                            <th
                                v-if="canManage || canViewPay"
                                class="px-4 py-2"
                            >
                                <span class="sr-only">Actions</span>
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr
                            v-for="assignment in assignments"
                            :key="assignment.id"
                        >
                            <td class="px-4 py-2">
                                <p class="font-medium">
                                    {{ assignment.project.name }}
                                </p>
                                <p class="text-muted-foreground">
                                    {{
                                        assignment.project.is_internal
                                            ? 'Internal'
                                            : (assignment.project.client ??
                                              'No Client')
                                    }}
                                </p>
                            </td>
                            <td class="px-4 py-2">
                                <ValueOr :value="assignment.job_title" />
                            </td>
                            <td class="px-4 py-2">
                                {{
                                    formatPeriod(
                                        assignment.start_date,
                                        assignment.end_date,
                                    )
                                }}
                            </td>
                            <td class="px-4 py-2 text-right">
                                {{ assignment.capacity }}%
                            </td>
                            <td v-if="canViewPay" class="px-4 py-2">
                                {{
                                    payInEffect(assignment)
                                        ? formatPay(payInEffect(assignment)!)
                                        : 'None in effect'
                                }}
                            </td>
                            <td
                                v-if="canManage || canViewPay"
                                class="px-4 py-2 text-right"
                            >
                                <div class="flex justify-end gap-2">
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
                            </td>
                        </tr>
                        <tr v-if="assignments.length === 0">
                            <td
                                :colspan="
                                    4 + (canViewPay ? 2 : canManage ? 1 : 0)
                                "
                                class="px-4 py-6 text-center text-muted-foreground"
                            >
                                No Assignments yet.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</template>

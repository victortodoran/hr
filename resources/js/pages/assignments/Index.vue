<script setup lang="ts">
import { Head, setLayoutProps } from '@inertiajs/vue3';
import AssignmentForm from '@/components/assignments/AssignmentForm.vue';
import Heading from '@/components/Heading.vue';
import PayDialog from '@/components/pay/PayDialog.vue';
import ValueOr from '@/components/ValueOr.vue';
import { formatPeriod } from '@/lib/dates';
import { formatPay } from '@/lib/pay';
import { index } from '@/routes/employees/assignments';
import type {
    AssignmentRecord,
    Choice,
    OpenProject,
    PayEntryRecord,
} from '@/types';

const props = defineProps<{
    employee: Choice;
    assignments: AssignmentRecord[];
    canManage: boolean;
    projects: OpenProject[];
    canViewPay: boolean;
    canManagePay: boolean;
    /** The company currency; only sent to those who may manage Pay. */
    currency?: string;
}>();

/**
 * The entry in effect today, or null when none has taken effect yet.
 */
function payInEffect(assignment: AssignmentRecord): PayEntryRecord | null {
    return assignment.pay?.find((entry) => entry.is_in_effect) ?? null;
}

setLayoutProps({
    breadcrumbs: [
        {
            title: `${props.employee.name}'s Assignments`,
            href: index(props.employee.id),
        },
    ],
});
</script>

<template>
    <Head :title="`${employee.name}'s Assignments`" />

    <div class="flex max-w-4xl flex-col gap-6 p-4">
        <div class="flex items-start justify-between gap-4">
            <Heading
                :title="`${employee.name}'s Assignments`"
                description="The Projects they work on, as what, when, for how much of their time and, where you may see it, for what Pay"
            />
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
                        <th v-if="canManage || canViewPay" class="px-4 py-2">
                            <span class="sr-only">Actions</span>
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <tr v-for="assignment in assignments" :key="assignment.id">
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
                            :colspan="4 + (canViewPay ? 2 : canManage ? 1 : 0)"
                            class="px-4 py-6 text-center text-muted-foreground"
                        >
                            No Assignments yet.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>

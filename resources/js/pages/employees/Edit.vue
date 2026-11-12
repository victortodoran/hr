<script setup lang="ts">
import { Form, Head, Link, setLayoutProps } from '@inertiajs/vue3';
import EmployeeController from '@/actions/App/Http/Controllers/EmployeeController';
import EmployeeInvitationController from '@/actions/App/Http/Controllers/EmployeeInvitationController';
import EmployeeFields from '@/components/employees/EmployeeFields.vue';
import MakeTopEmployee from '@/components/employees/MakeTopEmployee.vue';
import EmploymentStatusBadge from '@/components/employees/EmploymentStatusBadge.vue';
import Heading from '@/components/Heading.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import { Badge } from '@/components/ui/badge';
import { edit, show } from '@/routes/employees';
import type { Choice, EmployeeRecord, ManagerChoice } from '@/types';

const props = defineProps<{
    employee: EmployeeRecord;
    departments: Choice[];
    managers: ManagerChoice[];
    reportsNeedingAManager: Choice[];
}>();

setLayoutProps({
    breadcrumbs: [
        { title: props.employee.name, href: show(props.employee.id) },
        { title: 'Edit', href: edit(props.employee.id) },
    ],
});
</script>

<template>
    <Head :title="employee.name" />

    <div class="flex max-w-xl flex-col gap-8 p-4">
        <div class="flex items-start justify-between gap-4">
            <Heading :title="employee.name" description="Employee record" />
            <div class="flex flex-col items-end gap-2">
                <EmploymentStatusBadge :status="employee.status" />
                <Badge v-if="employee.is_top" variant="secondary"
                    >Top Employee</Badge
                >
            </div>
        </div>

        <div
            v-if="reportsNeedingAManager.length > 0"
            class="grid gap-2 rounded-lg border border-amber-300 p-4 text-sm dark:border-amber-500/40"
        >
            <p class="font-medium">
                {{ employee.name }} has left, but these Employees still report
                to them. Pick them a new Manager:
            </p>
            <ul class="list-inside list-disc">
                <li v-for="report in reportsNeedingAManager" :key="report.id">
                    <Link :href="edit(report.id)" class="underline">{{
                        report.name
                    }}</Link>
                </li>
            </ul>
        </div>

        <MakeTopEmployee v-if="employee.can_become_top" :employee="employee" />

        <div
            v-if="employee.can_resend_invitation"
            class="flex flex-wrap items-center justify-between gap-4 rounded-lg border p-4"
        >
            <p class="text-sm text-muted-foreground">
                {{ employee.name }} hasn't set a password yet.
            </p>
            <Form
                v-bind="EmployeeInvitationController.store.form(employee.id)"
                :options="{ preserveScroll: true }"
                v-slot="{ processing }"
            >
                <SubmitButton
                    :processing="processing"
                    variant="outline"
                    size="sm"
                    >Resend invitation</SubmitButton
                >
            </Form>
        </div>

        <Form
            v-bind="EmployeeController.update.form(employee.id)"
            :options="{ preserveScroll: true }"
            class="grid gap-6"
            v-slot="{ errors, processing }"
        >
            <EmployeeFields
                :employee="employee"
                :departments="departments"
                :managers="managers"
                :errors="errors"
            />

            <div>
                <SubmitButton :processing="processing">Save</SubmitButton>
            </div>
        </Form>
    </div>
</template>

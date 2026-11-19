<script setup lang="ts">
import { Form, Head, Link, setLayoutProps } from '@inertiajs/vue3';
import EmployeeController from '@/actions/App/Http/Controllers/Employees/EmployeeController';
import EmployeeInvitationController from '@/actions/App/Http/Controllers/Employees/EmployeeInvitationController';
import EmployeeFields from '@/components/employees/EmployeeFields.vue';
import EmploymentStatusBadge from '@/components/employees/EmploymentStatusBadge.vue';
import Heading from '@/components/Heading.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import { edit, show } from '@/routes/employees';
import type { Choice, EmployeeRecord, ManagerChoice } from '@/types';
import { withNoManagerAsEmpty } from '@/lib/employees';

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

    <div class="mx-auto flex w-full max-w-xl flex-col gap-8 p-4">
        <div class="flex items-start justify-between gap-4">
            <Heading :title="employee.name" description="Employee record" />
            <EmploymentStatusBadge :status="employee.status" />
        </div>

        <div
            v-if="reportsNeedingAManager.length > 0"
            class="grid gap-2 rounded-lg border border-amber-300 p-4 text-sm"
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
            :transform="withNoManagerAsEmpty"
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

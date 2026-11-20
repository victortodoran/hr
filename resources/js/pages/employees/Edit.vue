<script setup lang="ts">
import { Form, Head, Link, setLayoutProps } from '@inertiajs/vue3';
import EmployeeController from '@/actions/App/Http/Controllers/Employees/EmployeeController';
import EmployeeInvitationController from '@/actions/App/Http/Controllers/Employees/EmployeeInvitationController';
import EmployeeFields from '@/components/employees/EmployeeFields.vue';
import EmploymentStatusBadge from '@/components/employees/EmploymentStatusBadge.vue';
import PageHeader from '@/components/PageHeader.vue';
import PageContainer from '@/components/PageContainer.vue';
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

    <PageContainer width="form">
        <PageHeader :title="employee.name">
            <template #description>
                <span>Employee record</span>
                <span aria-hidden="true">·</span>
                <EmploymentStatusBadge :status="employee.status" />
            </template>
        </PageHeader>

        <div
            v-if="reportsNeedingAManager.length > 0"
            class="grid gap-2 rounded-lg border border-status-attention-icon/30 bg-status-attention p-4 text-body text-status-attention-foreground"
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
                    variant="secondary"
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
    </PageContainer>
</template>

<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import EmployeeController from '@/actions/App/Http/Controllers/Employees/EmployeeController';
import EmployeeFields from '@/components/employees/EmployeeFields.vue';
import PageHeader from '@/components/PageHeader.vue';
import PageContainer from '@/components/PageContainer.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import { Button } from '@/components/ui/button';
import { employeeFields, employeePayload } from '@/lib/employees';
import { create, index } from '@/routes/employees';
import type { Choice, ManagerChoice } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Employees', href: index() },
            { title: 'Add Employee', href: create() },
        ],
    },
});

/**
 * Adding an Employee (rule L13): their record and internal Job Title, in
 * the form template's sections. Adding them invites them to set a
 * password; Cancel returns to the Employees.
 */
defineProps<{
    departments: Choice[];
    managers: ManagerChoice[];
}>();

const form = useForm({ ...employeeFields(), job_title: '' });

function addEmployee(): void {
    form.transform(employeePayload).submit(EmployeeController.store());
}
</script>

<template>
    <Head title="Add Employee" />

    <PageContainer width="form">
        <PageHeader
            title="Add Employee"
            description="They'll get an email inviting them to set a password."
        />

        <form class="flex flex-col gap-8" @submit.prevent="addEmployee">
            <EmployeeFields
                v-model:name="form.name"
                v-model:email="form.email"
                v-model:department-id="form.department_id"
                v-model:manager-id="form.manager_id"
                v-model:start-date="form.start_date"
                v-model:leaving-date="form.leaving_date"
                v-model:job-title="form.job_title"
                :departments="departments"
                :managers="managers"
                :errors="form.errors"
            />

            <div class="flex flex-wrap gap-2">
                <SubmitButton :processing="form.processing"
                    >Add Employee</SubmitButton
                >
                <Button variant="secondary" as-child>
                    <Link :href="index()">Cancel</Link>
                </Button>
            </div>
        </form>
    </PageContainer>
</template>

<script setup lang="ts">
import { Form, Head, Link, setLayoutProps, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import EmployeeController from '@/actions/App/Http/Controllers/Employees/EmployeeController';
import EmployeeInvitationController from '@/actions/App/Http/Controllers/Employees/EmployeeInvitationController';
import Callout from '@/components/Callout.vue';
import EmployeeFields from '@/components/employees/EmployeeFields.vue';
import EmploymentStatusBadge from '@/components/employees/EmploymentStatusBadge.vue';
import PageHeader from '@/components/PageHeader.vue';
import PageContainer from '@/components/PageContainer.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import UnsavedChangesDialog from '@/components/UnsavedChangesDialog.vue';
import { useUnsavedChanges } from '@/composables/useUnsavedChanges';
import { employeeFields, employeePayload } from '@/lib/employees';
import { edit, index, show } from '@/routes/employees';
import type { Choice, EmployeeRecord, ManagerChoice } from '@/types';

const props = defineProps<{
    employee: EmployeeRecord;
    departments: Choice[];
    managers: ManagerChoice[];
    reportsNeedingAManager: Choice[];
}>();

setLayoutProps({
    breadcrumbs: [
        { title: 'Employees', href: index() },
        { title: props.employee.name, href: show(props.employee.id) },
        { title: 'Edit', href: edit(props.employee.id) },
    ],
});

const form = useForm(employeeFields(props.employee));

function save(): void {
    form.transform(employeePayload).submit(
        EmployeeController.update(props.employee.id),
        {
            preserveScroll: true,
            onSuccess: () => {
                form.defaults(employeeFields(props.employee));
                form.reset();
            },
        },
    );
}

const guard = useUnsavedChanges(computed(() => form.isDirty));
</script>

<template>
    <Head :title="`Edit ${employee.name}`" />

    <PageContainer width="form">
        <PageHeader :title="`Edit ${employee.name}`">
            <template #status>
                <EmploymentStatusBadge :status="employee.status" />
            </template>
        </PageHeader>

        <Callout
            v-if="reportsNeedingAManager.length > 0"
            tone="attention"
            data-test="reports-needing-a-manager"
        >
            <p>
                {{ employee.name }} has left, but these Employees still report
                to them. Pick them a new Manager:
            </p>
            <ul class="mt-1 list-inside list-disc">
                <li v-for="report in reportsNeedingAManager" :key="report.id">
                    <TextLink :href="edit(report.id)">{{
                        report.name
                    }}</TextLink>
                </li>
            </ul>
        </Callout>

        <Callout v-else-if="employee.can_resend_invitation" tone="info">
            {{ employee.name }} hasn't set a password yet.
            <template #action>
                <Form
                    v-bind="
                        EmployeeInvitationController.store.form(employee.id)
                    "
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
            </template>
        </Callout>

        <form class="flex flex-col gap-8" @submit.prevent="save">
            <EmployeeFields
                v-model:name="form.name"
                v-model:email="form.email"
                v-model:department-id="form.department_id"
                v-model:manager-id="form.manager_id"
                v-model:start-date="form.start_date"
                v-model:leaving-date="form.leaving_date"
                :status="employee.status"
                :departments="departments"
                :managers="managers"
                :errors="form.errors"
            />

            <div class="flex flex-wrap gap-2">
                <SubmitButton :processing="form.processing">Save</SubmitButton>
                <Button variant="secondary" as-child>
                    <Link :href="show(employee.id)">Cancel</Link>
                </Button>
            </div>
        </form>

        <UnsavedChangesDialog
            :open="guard.pending.value !== null"
            title="Discard changes"
            :description="`Your changes to ${employee.name}'s record aren't saved yet.`"
            @keep="guard.keep"
            @discard="guard.discard"
        />
    </PageContainer>
</template>

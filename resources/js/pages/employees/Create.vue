<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import EmployeeController from '@/actions/App/Http/Controllers/Employees/EmployeeController';
import EmployeeFields from '@/components/employees/EmployeeFields.vue';
import Heading from '@/components/Heading.vue';
import PageContainer from '@/components/PageContainer.vue';
import InputError from '@/components/InputError.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { create } from '@/routes/employees';
import type { Choice, ManagerChoice } from '@/types';
import { withNoManagerAsEmpty } from '@/lib/employees';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Add Employee', href: create() }],
    },
});

defineProps<{
    departments: Choice[];
    managers: ManagerChoice[];
}>();
</script>

<template>
    <Head title="Add Employee" />

    <PageContainer width="narrow">
        <Heading
            title="Add Employee"
            description="They'll get an email inviting them to set a password"
        />

        <Form
            v-bind="EmployeeController.store.form()"
            :transform="withNoManagerAsEmpty"
            class="grid gap-6"
            v-slot="{ errors, processing }"
        >
            <EmployeeFields
                :departments="departments"
                :managers="managers"
                :errors="errors"
            />

            <div class="grid gap-2">
                <Label for="job_title">Job Title</Label>
                <Input
                    id="job_title"
                    name="job_title"
                    required
                    maxlength="255"
                    autocomplete="off"
                />
                <p class="text-sm text-muted-foreground">
                    Their internal Job Title, shown in the Directory. Change it
                    later on the Assignments tab.
                </p>
                <InputError :message="errors.job_title" />
            </div>

            <div>
                <SubmitButton :processing="processing"
                    >Add Employee</SubmitButton
                >
            </div>
        </Form>
    </PageContainer>
</template>

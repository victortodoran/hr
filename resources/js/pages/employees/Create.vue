<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import EmployeeController from '@/actions/App/Http/Controllers/EmployeeController';
import EmployeeFields from '@/components/employees/EmployeeFields.vue';
import Heading from '@/components/Heading.vue';
import SubmitButton from '@/components/SubmitButton.vue';
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

    <div class="flex max-w-xl flex-col gap-8 p-4">
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

            <div>
                <SubmitButton :processing="processing"
                    >Add Employee</SubmitButton
                >
            </div>
        </Form>
    </div>
</template>

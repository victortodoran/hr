<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import EmployeeController from '@/actions/App/Http/Controllers/EmployeeController';
import EmployeeFields from '@/components/employees/EmployeeFields.vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { create } from '@/routes/employees';
import type { Choice } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Add Employee', href: create() }],
    },
});

defineProps<{
    departments: Choice[];
    managers: Choice[];
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
            class="grid gap-6"
            v-slot="{ errors, processing }"
        >
            <EmployeeFields
                :departments="departments"
                :managers="managers"
                :errors="errors"
            />

            <div>
                <Button type="submit" :disabled="processing">
                    <Spinner v-if="processing" />
                    Add Employee
                </Button>
            </div>
        </Form>
    </div>
</template>

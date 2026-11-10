<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import DepartmentController from '@/actions/App/Http/Controllers/DepartmentController';
import DeleteDepartment from '@/components/departments/DeleteDepartment.vue';
import RenameDepartment from '@/components/departments/RenameDepartment.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index } from '@/routes/departments';
import type { Department } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Departments', href: index() }],
    },
});

defineProps<{
    departments: Department[];
}>();
</script>

<template>
    <Head title="Departments" />

    <div class="flex max-w-2xl flex-col gap-8 p-4">
        <Heading
            title="Departments"
            description="The parts of the company each Employee belongs to"
        />

        <Form
            v-bind="DepartmentController.store.form()"
            reset-on-success
            :options="{ preserveScroll: true }"
            class="flex items-end gap-2"
            v-slot="{ errors, processing }"
        >
            <div class="grid flex-1 gap-2">
                <Label for="new-department">New Department</Label>
                <Input
                    id="new-department"
                    name="name"
                    placeholder="e.g. Finance"
                    required
                />
                <InputError :message="errors.name" />
            </div>
            <Button type="submit" :disabled="processing">Add</Button>
        </Form>

        <ul class="divide-y rounded-lg border">
            <li
                v-for="department in departments"
                :key="department.id"
                class="flex items-center justify-between gap-4 px-4 py-3"
            >
                <span class="font-medium">{{ department.name }}</span>
                <div class="flex gap-2">
                    <RenameDepartment :department="department" />
                    <DeleteDepartment :department="department" />
                </div>
            </li>
        </ul>
    </div>
</template>

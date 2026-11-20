<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { Building2 } from '@lucide/vue';
import DepartmentController from '@/actions/App/Http/Controllers/Company/DepartmentController';
import ConfirmDeleteDialog from '@/components/ConfirmDeleteDialog.vue';
import DataTable from '@/components/DataTable.vue';
import EmptyState from '@/components/EmptyState.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import PageContainer from '@/components/PageContainer.vue';
import RenameDialog from '@/components/RenameDialog.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index } from '@/routes/departments';
import type { Department, TableColumn } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Departments', href: index() }],
    },
});

defineProps<{
    departments: Department[];
}>();

const columns: TableColumn[] = [
    { key: 'name', label: 'Name' },
    { key: 'actions', label: 'Actions', align: 'end' },
];
</script>

<template>
    <Head title="Departments" />

    <PageContainer width="wide">
        <Heading
            title="Departments"
            description="The parts of the company each Employee belongs to"
        />

        <Form
            v-bind="DepartmentController.store.form()"
            reset-on-success
            :options="{ preserveScroll: true }"
            class="flex max-w-xl items-end gap-2"
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
            <SubmitButton :processing="processing">Add</SubmitButton>
        </Form>

        <DataTable :columns="columns" :rows="departments">
            <template #cell-name="{ row }">
                <span class="font-medium">{{ row.name }}</span>
            </template>
            <template #head-actions>
                <span class="sr-only">Actions</span>
            </template>
            <template #cell-actions="{ row }">
                <div class="flex justify-end gap-2">
                    <RenameDialog
                        :form="DepartmentController.update.form(row)"
                        :name="row.name"
                        :field-id="`department-name-${row.id}`"
                    />
                    <ConfirmDeleteDialog
                        :form="DepartmentController.destroy.form(row)"
                        :title="`Delete ${row.name}?`"
                        description="Only a Department with no Employees can be deleted."
                    />
                </div>
            </template>
            <template #empty>
                <EmptyState
                    :icon="Building2"
                    message="Departments are the parts of the company each Employee belongs to. There are none yet."
                >
                    <template #action>
                        <Button variant="secondary" as-child>
                            <label for="new-department">Add Department</label>
                        </Button>
                    </template>
                </EmptyState>
            </template>
        </DataTable>
    </PageContainer>
</template>

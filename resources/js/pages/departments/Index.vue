<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Building2, Plus } from '@lucide/vue';
import { computed, ref } from 'vue';
import DepartmentController from '@/actions/App/Http/Controllers/Company/DepartmentController';
import ConfirmDeleteDialog from '@/components/ConfirmDeleteDialog.vue';
import DataTable from '@/components/DataTable.vue';
import EmptyState from '@/components/EmptyState.vue';
import NameDialog from '@/components/NameDialog.vue';
import RowActions from '@/components/RowActions.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenuItem,
    DropdownMenuSeparator,
} from '@/components/ui/dropdown-menu';
import CompanySettingsLayout from '@/layouts/settings/CompanySettingsLayout.vue';
import { countOf } from '@/lib/roles';
import { index } from '@/routes/departments';
import { index as employees } from '@/routes/employees';
import type { Department, TableColumn } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Departments', href: index() }],
    },
});

const props = defineProps<{
    /** Each with how many of its Employees the viewer may see, when they may list Employees. */
    departments: (Department & { employee_count?: number })[];
    showsEmployeeCounts: boolean;
}>();

const columns = computed<TableColumn[]>(() => [
    { key: 'name', label: 'Name' },
    ...(props.showsEmployeeCounts
        ? [{ key: 'employee_count', label: 'Employees', align: 'end' as const }]
        : []),
    { key: 'actions', label: 'Actions', align: 'end' },
]);

const adding = ref(false);

/** The Department the Rename and Delete dialogs are about. */
const chosen = ref<Department & { employee_count?: number }>();
const renameOpen = ref(false);
const deleteOpen = ref(false);

function askToRename(department: Department): void {
    chosen.value = department;
    renameOpen.value = true;
}

function askToDelete(department: Department): void {
    chosen.value = department;
    deleteOpen.value = true;
}

const deleteDescription = computed(() => {
    const department = chosen.value;

    if (department?.employee_count) {
        return `${countOf(department.employee_count, 'Employee belongs', 'Employees belong')} to ${department.name}. Move them to another Department before deleting it.`;
    }

    return `${department?.name} is deleted for good. A Department that still has Employees can't be deleted.`;
});
</script>

<template>
    <Head title="Departments" />

    <CompanySettingsLayout>
        <section
            aria-labelledby="departments-heading"
            class="flex flex-col gap-4"
        >
            <header
                class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"
            >
                <div>
                    <h2 id="departments-heading" class="text-section">
                        Departments
                    </h2>
                    <p class="text-body text-muted-foreground">
                        The parts of the company each Employee belongs to.
                    </p>
                </div>
                <Button class="max-sm:h-11" @click="adding = true"
                    ><Plus />Add Department…</Button
                >
            </header>

            <DataTable :columns="columns" :rows="departments">
                <template #cell-name="{ row }">
                    <span class="font-medium">{{ row.name }}</span>
                </template>
                <template #cell-employee_count="{ row }">
                    <TextLink
                        :href="employees({ query: { department: row.id } })"
                        class="tabular-nums"
                        :aria-label="`${countOf(row.employee_count ?? 0, 'Employee', 'Employees')} in ${row.name}`"
                        >{{ row.employee_count }}</TextLink
                    >
                </template>
                <template #head-actions>
                    <span class="sr-only">Actions</span>
                </template>
                <template #cell-actions="{ row }">
                    <RowActions :label="row.name">
                        <template #menu>
                            <DropdownMenuItem @select="askToRename(row)"
                                >Rename…</DropdownMenuItem
                            >
                            <DropdownMenuSeparator />
                            <DropdownMenuItem
                                variant="destructive"
                                @select="askToDelete(row)"
                                >Delete…</DropdownMenuItem
                            >
                        </template>
                    </RowActions>
                </template>
                <template #empty>
                    <EmptyState
                        :icon="Building2"
                        message="Departments are the parts of the company each Employee belongs to. There are none yet."
                    >
                        <template #action>
                            <Button variant="secondary" @click="adding = true"
                                >Add Department…</Button
                            >
                        </template>
                    </EmptyState>
                </template>
            </DataTable>
        </section>

        <NameDialog
            v-model:open="adding"
            without-trigger
            :form="DepartmentController.store.form()"
            name=""
            field-id="new-department-name"
            title="Add Department"
            submit-label="Add Department"
        />
        <NameDialog
            v-if="chosen"
            :key="`rename-${chosen.id}`"
            v-model:open="renameOpen"
            without-trigger
            :form="DepartmentController.update.form(chosen)"
            :name="chosen.name"
            field-id="department-name"
        />
        <ConfirmDeleteDialog
            v-if="chosen"
            v-model:open="deleteOpen"
            without-trigger
            :form="DepartmentController.destroy.form(chosen)"
            :title="`Delete ${chosen.name}`"
            :description="deleteDescription"
            confirm-label="Delete Department"
        />
    </CompanySettingsLayout>
</template>

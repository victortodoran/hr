<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { UsersRound } from '@lucide/vue';
import DataTable from '@/components/DataTable.vue';
import EmploymentStatusBadge from '@/components/employees/EmploymentStatusBadge.vue';
import EmptyState from '@/components/EmptyState.vue';
import FilterBar from '@/components/FilterBar.vue';
import FilterSearch from '@/components/FilterSearch.vue';
import FilterSelect from '@/components/FilterSelect.vue';
import PageHeader from '@/components/PageHeader.vue';
import PageContainer from '@/components/PageContainer.vue';
import Pagination from '@/components/Pagination.vue';
import { Button } from '@/components/ui/button';
import ValueOr from '@/components/ValueOr.vue';
import { useListFilters } from '@/composables/useListFilters';
import { create, index, show } from '@/routes/employees';
import type {
    EmployeeListEntry,
    EmploymentStatus,
    FilterChoice,
    Paginated,
    TableColumn,
} from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Employees', href: index() }],
    },
});

const props = defineProps<{
    employees: Paginated<EmployeeListEntry>;
    search: string;
    status: EmploymentStatus | null;
    canAdd: boolean;
}>();

const columns: TableColumn[] = [
    { key: 'name', label: 'Name' },
    { key: 'status', label: 'Status' },
    { key: 'department', label: 'Department', minor: true },
    { key: 'manager', label: 'Manager', minor: true },
];

const statuses: FilterChoice[] = [
    { value: 'future', label: 'Future' },
    { value: 'current', label: 'Current' },
    { value: 'former', label: 'Former' },
];

const filters = useListFilters(index.url, {
    search: props.search,
    status: props.status ?? 'all',
});
</script>

<template>
    <Head title="Employees" />

    <PageContainer width="full">
        <PageHeader
            title="Employees"
            description="Everyone whose record you may see: future, current and former"
        >
            <template v-if="canAdd" #actions>
                <Button as-child>
                    <Link :href="create()">Add Employee</Link>
                </Button>
            </template>
        </PageHeader>

        <FilterBar>
            <FilterSearch v-model="filters.search" />
            <FilterSelect
                v-model="filters.status"
                label="Status"
                any="Any status"
                :choices="statuses"
            />
        </FilterBar>

        <DataTable :columns="columns" :rows="employees.data">
            <template #cell-name="{ row }">
                <Link
                    :href="show(row.id)"
                    class="font-medium underline-offset-4 hover:underline"
                    >{{ row.name }}</Link
                >
            </template>
            <template #cell-status="{ row }">
                <EmploymentStatusBadge :status="row.status" />
            </template>
            <template #cell-department="{ row }">
                <ValueOr :value="row.department" />
            </template>
            <template #cell-manager="{ row }">
                <span v-if="row.manager">{{ row.manager.name }}</span>
                <span v-else class="text-muted-foreground">None</span>
            </template>
            <template #empty>
                <EmptyState
                    :icon="UsersRound"
                    message="Employees whose record you may see appear here. None match your search and status."
                >
                    <template v-if="canAdd" #action>
                        <Button as-child>
                            <Link :href="create()">Add Employee</Link>
                        </Button>
                    </template>
                </EmptyState>
            </template>
        </DataTable>

        <Pagination :page="employees" />
    </PageContainer>
</template>

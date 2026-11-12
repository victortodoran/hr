<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { UsersRound } from '@lucide/vue';
import { useDebounceFn } from '@vueuse/core';
import { ref } from 'vue';
import DataTable from '@/components/DataTable.vue';
import EmploymentStatusBadge from '@/components/employees/EmploymentStatusBadge.vue';
import EmptyState from '@/components/EmptyState.vue';
import Heading from '@/components/Heading.vue';
import PageContainer from '@/components/PageContainer.vue';
import Pagination from '@/components/Pagination.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import ValueOr from '@/components/ValueOr.vue';
import { create, index, show } from '@/routes/employees';
import type {
    EmployeeListEntry,
    EmploymentStatus,
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

const search = ref(props.search);
const statusFilter = ref<string>(props.status ?? 'all');

function reloadList(): void {
    router.get(
        index.url({
            query: {
                ...(search.value ? { search: search.value } : {}),
                ...(statusFilter.value !== 'all'
                    ? { status: statusFilter.value }
                    : {}),
            },
        }),
        {},
        { preserveState: true, replace: true },
    );
}

const searchEmployees = useDebounceFn(reloadList, 300);
</script>

<template>
    <Head title="Employees" />

    <PageContainer width="wide">
        <div class="flex items-start justify-between gap-4">
            <Heading
                title="Employees"
                description="Everyone whose record you may see: future, current and former"
            />
            <Button v-if="canAdd" as-child>
                <Link :href="create()">Add Employee</Link>
            </Button>
        </div>

        <div class="flex flex-wrap gap-3">
            <Input
                v-model="search"
                type="search"
                placeholder="Search by name"
                aria-label="Search by name"
                class="max-w-sm"
                @update:model-value="searchEmployees"
            />
            <Select v-model="statusFilter" @update:model-value="reloadList">
                <SelectTrigger class="w-44" aria-label="Status">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="all">Any status</SelectItem>
                    <SelectItem value="future">Future</SelectItem>
                    <SelectItem value="current">Current</SelectItem>
                    <SelectItem value="former">Former</SelectItem>
                </SelectContent>
            </Select>
        </div>

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
                <span v-else class="text-muted-foreground"
                    >None (top Employee)</span
                >
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

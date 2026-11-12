<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { UsersRound } from '@lucide/vue';
import DataTable from '@/components/DataTable.vue';
import EmptyState from '@/components/EmptyState.vue';
import ValueOr from '@/components/ValueOr.vue';
import { formatPeriod } from '@/lib/dates';
import { show } from '@/routes/employees';
import type { TableColumn, TeamAssignment } from '@/types';

defineProps<{
    assignments: TeamAssignment[];
    empty: string;
}>();

const columns: TableColumn[] = [
    { key: 'employee', label: 'Employee' },
    { key: 'job_title', label: 'Job Title', minor: true },
    { key: 'dates', label: 'Dates', minor: true },
    { key: 'capacity', label: 'Capacity', align: 'end' },
];
</script>

<template>
    <DataTable :columns="columns" :rows="assignments">
        <template #cell-employee="{ row }">
            <Link
                :href="show(row.employee.id)"
                class="font-medium underline-offset-4 hover:underline"
                >{{ row.employee.name }}</Link
            >
        </template>
        <template #cell-job_title="{ row }">
            <ValueOr :value="row.job_title" />
        </template>
        <template #cell-dates="{ row }">
            {{ formatPeriod(row.start_date, row.end_date) }}
        </template>
        <template #cell-capacity="{ row }"> {{ row.capacity }}% </template>
        <template #empty>
            <EmptyState :icon="UsersRound" :message="empty" />
        </template>
    </DataTable>
</template>

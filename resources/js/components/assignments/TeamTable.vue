<script setup lang="ts" generic="Row extends TeamAssignment">
import { UsersRound } from '@lucide/vue';
import CapacityBar from '@/components/assignments/CapacityBar.vue';
import DataTable from '@/components/DataTable.vue';
import EmptyState from '@/components/EmptyState.vue';
import PersonCell from '@/components/PersonCell.vue';
import ValueOr from '@/components/ValueOr.vue';
import { formatPeriod } from '@/lib/dates';
import { show } from '@/routes/employees';
import type { TableColumn, TeamAssignment } from '@/types';

/**
 * A team as a Project's or a Client's page lists it: each Assignment's
 * Employee (K13), Job Title, dates and Capacity, the Capacity as a
 * `CapacityBar` (F11). Given `groupBy` and `groups`, it groups
 * the Assignments, such as by Project on a Client's page, each group row
 * filled by the `group` slot.
 */
defineProps<{
    assignments: Row[];
    empty: string;
    groupBy?: (row: Row) => string;
    groups?: string[];
}>();

defineSlots<{
    group?: (props: { group: string; rows: Row[] }) => unknown;
}>();

const columns: TableColumn[] = [
    { key: 'employee', label: 'Employee', phone: 'primary' },
    { key: 'job_title', label: 'Job Title', phone: 'secondary' },
    { key: 'dates', label: 'Dates', phone: 'secondary' },
    { key: 'capacity', label: 'Capacity', align: 'end', phone: 'end' },
];
</script>

<template>
    <DataTable
        :columns="columns"
        :rows="assignments"
        :group-by="groupBy"
        :groups="groups"
    >
        <template v-if="$slots.group" #group="{ group, rows }">
            <slot name="group" :group="group" :rows="rows" />
        </template>
        <template #cell-employee="{ row }">
            <PersonCell
                :name="row.employee.name"
                :href="show(row.employee.id)"
            />
        </template>
        <template #cell-job_title="{ row }">
            <ValueOr :value="row.job_title" />
        </template>
        <template #cell-dates="{ row }">
            <span class="tabular-nums">{{
                formatPeriod(row.start_date, row.end_date)
            }}</span>
        </template>
        <template #cell-capacity="{ row }">
            <CapacityBar :capacity="row.capacity" />
        </template>
        <template #empty>
            <EmptyState :icon="UsersRound" :message="empty" />
        </template>
    </DataTable>
</template>

<script setup lang="ts" generic="Row extends TeamAssignment">
import { UsersRound } from '@lucide/vue';
import DataTable from '@/components/DataTable.vue';
import EmptyState from '@/components/EmptyState.vue';
import PersonCell from '@/components/PersonCell.vue';
import ValueOr from '@/components/ValueOr.vue';
import { formatPeriod } from '@/lib/dates';
import { show } from '@/routes/employees';
import type { TableColumn, TeamAssignment } from '@/types';

/**
 * A team as a Project's or a Client's page lists it: each Assignment's
 * Employee (K13), Job Title, dates and Capacity, the Capacity as a small
 * bar beside its percentage (F11). Given `groupBy` and `groups`, it groups
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
            <span
                class="inline-flex items-center justify-end gap-2"
                data-test="capacity"
            >
                <span
                    class="hidden h-1.5 w-16 overflow-hidden rounded-full bg-muted sm:block"
                    aria-hidden="true"
                >
                    <span
                        class="block h-full rounded-full bg-chart-bar-strong"
                        :style="{ width: `${Math.min(row.capacity, 100)}%` }"
                    />
                </span>
                <span class="w-10 text-right tabular-nums"
                    >{{ row.capacity }}%</span
                >
            </span>
        </template>
        <template #empty>
            <EmptyState :icon="UsersRound" :message="empty" />
        </template>
    </DataTable>
</template>

<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { CalendarDays } from '@lucide/vue';
import DataTable from '@/components/DataTable.vue';
import EmptyState from '@/components/EmptyState.vue';
import PageHeader from '@/components/PageHeader.vue';
import ScopeSwitch from '@/components/ScopeSwitch.vue';
import ListFilterBar from '@/components/ListFilterBar.vue';
import PageContainer from '@/components/PageContainer.vue';
import Pagination from '@/components/Pagination.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { reloadList } from '@/composables/useListFilters';
import { formatDateRange } from '@/lib/dates';
import { show as employeePage } from '@/routes/employees';
import { index } from '@/routes/time-off-list';
import type {
    EmployeeWithCount,
    ListedTimeOff,
    Paginated,
    StateChoice,
    TableColumn,
    TimeOffListFilters,
    TimeOffState,
} from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Time Off', href: index() }],
    },
});

/**
 * The Time Off of the Employees the viewer may see Time Off for, a Period
 * at a time, for every Employee or one. Each Employee's Paid Days and all
 * their Time Off are on the Time Off tab of their page.
 */
const props = defineProps<{
    filters: TimeOffListFilters;
    today: string;
    timeOff: Paginated<ListedTimeOff>;
    /** Who has Time Off in the Period, by name, with how many. */
    employees: EmployeeWithCount[];
}>();

const states: StateChoice<TimeOffState>[] = [
    { value: 'pending', label: 'Pending' },
    { value: 'approved', label: 'Approved' },
    { value: 'declined', label: 'Declined' },
    { value: 'cancelled', label: 'Cancelled' },
];

const columns: TableColumn[] = [
    { key: 'employee', label: 'Employee' },
    { key: 'dates', label: 'Dates' },
    { key: 'days', label: 'Days', align: 'end', minor: true },
    { key: 'kind', label: 'Kind', minor: true },
    { key: 'state', label: 'State', align: 'end' },
];

function showFiltered(query: Record<string, string>): void {
    reloadList(index.url({ query }));
}
</script>

<template>
    <Head title="Time Off" />

    <PageContainer width="full">
        <PageHeader
            title="Time Off"
            description="Who is away from work, and when"
        />
        <ScopeSwitch area="timeOff" />

        <ListFilterBar
            :filters="props.filters"
            :today="today"
            :employees="employees"
            :states="states"
            allow-future
            @change="showFiltered"
        />

        <div class="flex flex-col gap-3">
            <DataTable :columns="columns" :rows="timeOff.data">
                <template #cell-employee="{ row }">
                    <Link
                        :href="
                            employeePage(row.employee.id, {
                                query: { tab: 'time-off' },
                            })
                        "
                        class="font-medium underline-offset-4 hover:underline"
                        >{{ row.employee.name }}</Link
                    >
                </template>
                <template #cell-dates="{ row }">
                    <span class="whitespace-nowrap">{{
                        formatDateRange(row.first_date, row.last_date)
                    }}</span>
                </template>
                <template #cell-days="{ row }">
                    <span class="tabular-nums">{{ row.days }}</span>
                </template>
                <template #cell-kind="{ row }">
                    {{ row.is_paid ? 'Paid' : 'Unpaid' }}
                </template>
                <template #cell-state="{ row }">
                    <StatusBadge :state="row.state" />
                </template>
                <template #empty>
                    <EmptyState
                        :icon="CalendarDays"
                        :message="
                            employees.length > 0
                                ? 'No Time Off matches these filters.'
                                : 'Time Off with a date in this period appears here. There is none.'
                        "
                    />
                </template>
            </DataTable>

            <Pagination :page="timeOff" preserve-state />
        </div>
    </PageContainer>
</template>

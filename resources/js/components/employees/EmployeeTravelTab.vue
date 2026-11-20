<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Luggage } from '@lucide/vue';
import { computed } from 'vue';
import DataTable from '@/components/DataTable.vue';
import EmptyState from '@/components/EmptyState.vue';
import EmployeeListTab from '@/components/employees/EmployeeListTab.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { formatDateRange } from '@/lib/dates';
import { show as travelRequestPage } from '@/routes/travel-requests';
import { index as travelRequestList } from '@/routes/travel-request-list';
import type {
    Choice,
    ListedTravelRequest,
    Paginated,
    Period,
    TableColumn,
} from '@/types';

const props = defineProps<{
    employee: Choice;
    travel: {
        today: string;
        period: Period;
        travelRequests: Paginated<ListedTravelRequest>;
        total: string;
    };
}>();

const columns: TableColumn[] = [
    { key: 'travel', label: 'Travel Request' },
    { key: 'project', label: 'Project', minor: true },
    { key: 'state', label: 'Status' },
    { key: 'expenses', label: 'Expenses', align: 'end' },
];

const stats = computed(() => [
    {
        label:
            props.travel.travelRequests.total === 1
                ? 'Travel Request'
                : 'Travel Requests',
        value: String(props.travel.travelRequests.total),
    },
    { label: 'Spent, Declined left out', value: props.travel.total },
]);
</script>

<template>
    <EmployeeListTab
        :employee="employee"
        tab="travel"
        :period="travel.period"
        :today="travel.today"
        :stats="stats"
        title="Travel Requests"
        list-name="Travel Requests"
        :list-href="
            travelRequestList({
                query: { employee: employee.id, ...travel.period.query },
            })
        "
        :page="travel.travelRequests"
    >
        <DataTable :columns="columns" :rows="travel.travelRequests.data">
            <template #cell-travel="{ row }">
                <p data-test="travel-request">
                    <Link
                        :href="travelRequestPage(row.id)"
                        class="font-medium underline-offset-4 hover:underline"
                        >{{ row.destination }}</Link
                    >
                    <span class="block text-muted-foreground">{{
                        formatDateRange(row.first_date, row.last_date)
                    }}</span>
                </p>
            </template>
            <template #cell-project="{ row }">
                {{ row.project }}
            </template>
            <template #cell-state="{ row }">
                <StatusBadge :state="row.state" />
            </template>
            <template #cell-expenses="{ row }">
                <span v-if="row.expenses" class="whitespace-nowrap tabular-nums"
                    >{{ row.expenses.total
                    }}<span
                        v-if="row.expenses.pending > 0"
                        class="block text-muted-foreground"
                        >{{ row.expenses.pending }} Pending</span
                    ></span
                >
                <span v-else class="text-muted-foreground">—</span>
            </template>
            <template #empty>
                <EmptyState
                    :icon="Luggage"
                    :message="`${employee.name}'s Travel Requests with a day in this period appear here. There are none.`"
                />
            </template>
        </DataTable>
    </EmployeeListTab>
</template>

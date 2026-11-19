<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Luggage } from '@lucide/vue';
import DataTable from '@/components/DataTable.vue';
import EmptyState from '@/components/EmptyState.vue';
import Heading from '@/components/Heading.vue';
import ListFilterBar from '@/components/ListFilterBar.vue';
import PageContainer from '@/components/PageContainer.vue';
import Pagination from '@/components/Pagination.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import TravelExpenseExportMenu from '@/components/travel-requests/TravelExpenseExportMenu.vue';
import { reloadList } from '@/composables/useListFilters';
import { formatDateRange } from '@/lib/dates';
import { index } from '@/routes/travel-request-list';
import { show } from '@/routes/travel-requests';
import type {
    Choice,
    EmployeeWithCount,
    ListedTravelRequest,
    Paginated,
    StateChoice,
    TableColumn,
    TravelRequestListFilters,
    TravelRequestState,
} from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Travel Requests', href: index() }],
    },
});

/**
 * The Travel Requests of the Employees the viewer may see travel of, a
 * Period at a time, for every Employee or one, each with what was spent
 * on it. Its Travel Expenses, their receipts and reopening them are on the
 * Travel Request's own page.
 */
const props = defineProps<{
    filters: TravelRequestListFilters;
    today: string;
    travelRequests: Paginated<ListedTravelRequest>;
    /** The total of the matching Travel Requests' Travel Expenses that aren't Declined, in the company currency. */
    total: string;
    /** Who has Travel Requests in the Period, by name, with how many. */
    employees: EmployeeWithCount[];
    /** The Projects the Employee shown, or anyone, has Travel Requests on in the Period. */
    projects: Choice[];
}>();

const states: StateChoice<TravelRequestState>[] = [
    { value: 'pending', label: 'Pending' },
    { value: 'approved', label: 'Approved' },
    { value: 'declined', label: 'Declined' },
    { value: 'cancelled', label: 'Cancelled' },
];

const columns: TableColumn[] = [
    { key: 'employee', label: 'Employee' },
    { key: 'travel', label: 'Travel Request' },
    { key: 'project', label: 'Project', minor: true },
    { key: 'state', label: 'Status' },
    { key: 'expenses', label: 'Expenses', align: 'end' },
];

function showFiltered(query: Record<string, string>): void {
    reloadList(index.url({ query }));
}
</script>

<template>
    <Head title="Travel Requests" />

    <PageContainer width="wide">
        <Heading
            title="Travel Requests"
            description="Employees' travel and what they spent on it"
        >
            <template #actions>
                <TravelExpenseExportMenu :filters="props.filters" />
            </template>
        </Heading>

        <ListFilterBar
            :filters="props.filters"
            :today="today"
            :employees="employees"
            :projects="projects"
            :states="states"
            allow-future
            @change="showFiltered"
        />

        <p
            v-if="travelRequests.total > 0"
            class="text-sm text-muted-foreground"
        >
            <span class="font-medium text-foreground tabular-nums">{{
                total
            }}</span>
            spent on {{ travelRequests.total }} matching
            {{
                travelRequests.total === 1
                    ? 'Travel Request'
                    : 'Travel Requests'
            }}, Declined expenses left out
        </p>

        <div class="flex flex-col gap-3">
            <DataTable :columns="columns" :rows="travelRequests.data">
                <template #cell-employee="{ row }">
                    {{ row.employee.name }}
                </template>
                <template #cell-travel="{ row }">
                    <p data-test="travel-request">
                        <Link
                            :href="show(row.id)"
                            class="font-medium underline-offset-4 hover:underline"
                            >{{ row.destination }}</Link
                        >
                        <span class="text-muted-foreground">
                            ·
                            {{
                                formatDateRange(row.first_date, row.last_date)
                            }}</span
                        >
                    </p>
                </template>
                <template #cell-project="{ row }">
                    {{ row.project }}
                </template>
                <template #cell-state="{ row }">
                    <StatusBadge :state="row.state" />
                </template>
                <template #cell-expenses="{ row }">
                    <span
                        v-if="row.expenses"
                        class="whitespace-nowrap tabular-nums"
                        >{{ row.expenses.total
                        }}<span
                            v-if="row.expenses.pending > 0"
                            class="text-muted-foreground"
                        >
                            · {{ row.expenses.pending }} Pending</span
                        ></span
                    >
                    <span v-else class="text-muted-foreground">—</span>
                </template>
                <template #empty>
                    <EmptyState
                        :icon="Luggage"
                        :message="
                            employees.length > 0
                                ? 'No Travel Requests match these filters.'
                                : 'Travel Requests with a day in this period appear here. There are none.'
                        "
                    />
                </template>
            </DataTable>

            <Pagination :page="travelRequests" preserve-state />
        </div>
    </PageContainer>
</template>

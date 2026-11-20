<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { Luggage } from '@lucide/vue';
import { computed } from 'vue';
import DataTable from '@/components/DataTable.vue';
import EmptyState from '@/components/EmptyState.vue';
import EmployeePageSection from '@/components/employees/EmployeePageSection.vue';
import Pagination from '@/components/Pagination.vue';
import PeriodNavigation from '@/components/PeriodNavigation.vue';
import StatStrip from '@/components/StatStrip.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { formatDateRange } from '@/lib/dates';
import { show } from '@/routes/employees';
import { show as travelRequestPage } from '@/routes/travel-requests';
import { index as travelRequestList } from '@/routes/travel-request-list';
import type {
    Choice,
    ListedTravelRequest,
    Paginated,
    Period,
    TableColumn,
} from '@/types';

/**
 * The Travel tab of an Employee's page: their Travel Requests with a day in
 * a Period, as the Travel Requests page lists them for this Employee,
 * read-only. Export stays on that page, which "Open in Travel Requests"
 * opens on the same Period.
 */
const props = defineProps<{
    employee: Choice;
    travel: {
        today: string;
        period: Period;
        travelRequests: Paginated<ListedTravelRequest>;
        /** Their Travel Expenses that aren't Declined, in the company currency. */
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

function choosePeriod(period: Record<string, string>): void {
    router.get(
        show.url(props.employee.id, { query: { tab: 'travel', ...period } }),
        {},
        { preserveScroll: true },
    );
}
</script>

<template>
    <div class="flex flex-col gap-6">
        <PeriodNavigation
            :period="travel.period"
            :today="travel.today"
            @change="choosePeriod"
        />

        <StatStrip v-if="travel.travelRequests.total > 0" :stats="stats" />

        <EmployeePageSection title="Travel Requests">
            <template #action>
                <Button variant="secondary" size="sm" as-child>
                    <Link
                        :href="
                            travelRequestList({
                                query: {
                                    employee: employee.id,
                                    ...travel.period.query,
                                },
                            })
                        "
                        >Open in Travel Requests</Link
                    >
                </Button>
            </template>

            <div class="flex flex-col gap-3">
                <DataTable
                    :columns="columns"
                    :rows="travel.travelRequests.data"
                >
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
                        <span
                            v-if="row.expenses"
                            class="whitespace-nowrap tabular-nums"
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

                <Pagination :page="travel.travelRequests" />
            </div>
        </EmployeePageSection>
    </div>
</template>

<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { Plane } from '@lucide/vue';
import { computed } from 'vue';
import DataTable from '@/components/DataTable.vue';
import EmptyState from '@/components/EmptyState.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { formatDateRange } from '@/lib/dates';
import { show } from '@/routes/travel-requests';
import type { TableColumn, TravelHistory, TravelRequestSummary } from '@/types';

const props = defineProps<{
    travelRequests: TravelHistory;
}>();

type Row = TravelRequestSummary & { group: 'upcoming' | 'past' };

const groupTitles = { upcoming: 'Upcoming', past: 'Past' } as const;

const columns: TableColumn[] = [
    { key: 'destination', label: 'Destination', phone: 'primary' },
    { key: 'dates', label: 'Dates', phone: 'secondary' },
    { key: 'project', label: 'Project', phone: 'secondary' },
    { key: 'state', label: 'Status', phone: 'end' },
    { key: 'expenses', label: 'Expenses', align: 'end', minor: true },
    { key: 'approved', label: 'Approved', align: 'end', minor: true },
];

const rows = computed<Row[]>(() => [
    ...props.travelRequests.upcoming.map((row) => ({
        ...row,
        group: 'upcoming' as const,
    })),
    ...props.travelRequests.past.map((row) => ({
        ...row,
        group: 'past' as const,
    })),
]);

function open(row: Row): void {
    router.visit(show(row.id));
}
</script>

<template>
    <DataTable
        :columns="columns"
        :rows="rows"
        :group-by="(row) => row.group"
        :on-row-click="open"
    >
        <template #group="{ group }">
            <span class="font-semibold">{{
                groupTitles[group as Row['group']]
            }}</span>
        </template>
        <template #cell-destination="{ row }">
            <Link
                :href="show(row.id)"
                class="font-medium underline-offset-4 hover:underline"
                >{{ row.destination }}</Link
            >
        </template>
        <template #cell-dates="{ row }">
            <span class="whitespace-nowrap tabular-nums">{{
                formatDateRange(row.first_date, row.last_date)
            }}</span>
        </template>
        <template #cell-project="{ row }">
            {{ row.project
            }}<span v-if="row.client" class="text-muted-foreground">
                · {{ row.client }}</span
            >
        </template>
        <template #cell-state="{ row }">
            <StatusBadge :state="row.state" />
        </template>
        <template #cell-expenses="{ row }">
            <span class="whitespace-nowrap tabular-nums">{{
                row.entered_total ?? ''
            }}</span>
            <span
                v-if="row.declined_expenses > 0"
                class="block text-caption whitespace-nowrap text-status-declined-foreground"
                >{{ row.declined_expenses }} Declined</span
            >
        </template>
        <template #cell-approved="{ row }">
            <span class="whitespace-nowrap tabular-nums">{{
                row.approved_total ?? ''
            }}</span>
        </template>
        <template #empty>
            <EmptyState
                :icon="Plane"
                message="Travel you request appears here, coming up first. You haven't requested any yet."
            />
        </template>
    </DataTable>
</template>

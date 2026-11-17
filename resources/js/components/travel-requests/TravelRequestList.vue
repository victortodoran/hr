<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Plane } from '@lucide/vue';
import DataTable from '@/components/DataTable.vue';
import EmptyState from '@/components/EmptyState.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { formatDateRange } from '@/lib/dates';
import { show } from '@/routes/travel-requests';
import type { TableColumn, TravelHistory } from '@/types';

/**
 * An Employee's Travel Requests under Upcoming and Past, one short row
 * each that opens the Travel Request's page. On a phone each row stacks
 * into two lines: destination and dates, then Client, expenses and state.
 */
defineProps<{
    travelRequests: TravelHistory;
    /** What each section's empty state says. */
    emptyMessages: { upcoming: string; past: string };
}>();

const columns: TableColumn[] = [
    { key: 'travel', label: 'Travel Request' },
    { key: 'client', label: 'Client', minor: true },
    { key: 'expenses', label: 'Expenses', align: 'end', minor: true },
    { key: 'state', label: 'State', align: 'end', minor: true },
];

const sections = [
    { key: 'upcoming', title: 'Upcoming' },
    { key: 'past', title: 'Past' },
] as const;
</script>

<template>
    <div class="flex flex-col gap-8">
        <section
            v-for="section in sections"
            :key="section.key"
            class="flex flex-col gap-2"
        >
            <h2 class="text-sm font-medium">{{ section.title }}</h2>
            <DataTable :columns="columns" :rows="travelRequests[section.key]">
                <template #cell-travel="{ row }">
                    <p>
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
                    <div
                        class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-muted-foreground sm:hidden"
                    >
                        <span v-if="row.client">{{ row.client }} ·</span>
                        <span class="tabular-nums"
                            >{{ row.expense_total ?? '—'
                            }}<span
                                v-if="row.declined_expenses > 0"
                                class="text-destructive"
                            >
                                · {{ row.declined_expenses }} declined</span
                            ></span
                        >
                        <StatusBadge :state="row.state" />
                    </div>
                </template>
                <template #cell-client="{ row }">
                    {{ row.client ?? '—' }}
                </template>
                <template #cell-expenses="{ row }">
                    <span class="whitespace-nowrap tabular-nums">{{
                        row.expense_total ?? '—'
                    }}</span>
                    <span
                        v-if="row.declined_expenses > 0"
                        class="block whitespace-nowrap text-destructive"
                        >· {{ row.declined_expenses }} declined</span
                    >
                </template>
                <template #cell-state="{ row }">
                    <StatusBadge :state="row.state" />
                </template>
                <template #empty>
                    <EmptyState
                        :icon="Plane"
                        :message="emptyMessages[section.key]"
                    />
                </template>
            </DataTable>
        </section>
    </div>
</template>

<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import type { InertiaLinkProps } from '@inertiajs/vue3';
import DataTable from '@/components/DataTable.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { formatDate, formatDateRange } from '@/lib/dates';
import { formatHours } from '@/lib/work-entries';
import { index as timeOff } from '@/routes/time-off';
import { show as travelRequest } from '@/routes/travel-requests';
import { index as workEntries } from '@/routes/work-entries';
import type { NeedsAttention, NeedsAttentionItem, TableColumn } from '@/types';

/**
 * The signed-in Employee's Declined Work Entries, Travel Requests, Travel
 * Expenses and Time Off, each with its reason and a link to where it can
 * be changed.
 */
defineProps<{
    needsAttention: NeedsAttention;
}>();

const columns: TableColumn[] = [
    { key: 'item', label: 'What' },
    { key: 'decline_reason', label: 'Reason' },
];

/** What the item is, as a row's first column reads. */
function kindOf(item: NeedsAttentionItem): string {
    return {
        'work-entry': 'Work Entry',
        'travel-request': 'Travel Request',
        'travel-expense': 'Travel Expense',
        'time-off': 'Time Off',
    }[item.kind];
}

/** Which one it is, such as "10 Nov 2026, Relaunch, 1.5 h". */
function summaryOf(item: NeedsAttentionItem): string {
    switch (item.kind) {
        case 'work-entry':
            return `${formatDate(item.date)}, ${item.project}, ${formatHours(item.hours)}`;
        case 'travel-request':
            return `${item.destination}, ${formatDateRange(item.first_date, item.last_date)}`;
        case 'travel-expense':
            return `${item.description} (${item.destination})`;
        case 'time-off':
            return formatDateRange(item.first_date, item.last_date);
    }
}

/**
 * Where the item can be changed: the Work page on its week, the Travel
 * Request (holding the Travel Expense), or Time Off.
 */
function linkOf(
    item: NeedsAttentionItem,
): NonNullable<InertiaLinkProps['href']> {
    switch (item.kind) {
        case 'work-entry':
            return workEntries({ query: { week: item.date } });
        case 'travel-request':
            return travelRequest(item.id);
        case 'travel-expense':
            return travelRequest(item.travel_request_id);
        case 'time-off':
            return timeOff();
    }
}
</script>

<template>
    <Card data-test="needs-attention">
        <CardHeader>
            <CardTitle>Needs your attention</CardTitle>
            <CardDescription
                >What your Manager declined, and why</CardDescription
            >
        </CardHeader>
        <CardContent class="space-y-3 text-sm">
            <DataTable
                :columns="columns"
                :rows="needsAttention.items"
                :row-key="(item) => `${item.kind}-${item.id}`"
            >
                <template #cell-item="{ row }">
                    <div class="flex flex-col gap-1">
                        <span class="flex items-center gap-2">
                            <StatusBadge state="declined" />
                            <span class="font-medium">{{ kindOf(row) }}</span>
                        </span>
                        <Link
                            :href="linkOf(row)"
                            class="underline underline-offset-4"
                            >{{ summaryOf(row) }}</Link
                        >
                    </div>
                </template>
            </DataTable>
            <p v-if="needsAttention.more > 0" class="text-muted-foreground">
                And {{ needsAttention.more }} more, shown once these are dealt
                with.
            </p>
        </CardContent>
    </Card>
</template>

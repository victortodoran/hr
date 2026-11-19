<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { CalendarDays } from '@lucide/vue';
import TimeOffCancellationController from '@/actions/App/Http/Controllers/TimeOff/TimeOffCancellationController';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import DataTable from '@/components/DataTable.vue';
import EmptyState from '@/components/EmptyState.vue';
import Heading from '@/components/Heading.vue';
import PageContainer from '@/components/PageContainer.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import PaidDaysLeft from '@/components/time-off/PaidDaysLeft.vue';
import TimeOffSummary from '@/components/time-off/TimeOffSummary.vue';
import { Button } from '@/components/ui/button';
import { formatDateRange } from '@/lib/dates';
import { create, index } from '@/routes/time-off';
import type { PaidDaysLeftInYear, TableColumn, TimeOffRecord } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Time Off', href: index() }],
    },
});

defineProps<{
    /** This year's Paid Days left and, from October, next year's. */
    paidDaysLeft: PaidDaysLeftInYear[];
    timeOff: TimeOffRecord[];
}>();

const columns: TableColumn[] = [
    { key: 'time_off', label: 'Time Off' },
    { key: 'state', label: 'State', align: 'end' },
];
</script>

<template>
    <Head title="Time Off" />

    <PageContainer width="wide">
        <Heading
            title="Time Off"
            description="Request days away from work, Paid or Unpaid, and cancel them before they start"
        />

        <div class="flex flex-wrap items-center justify-between gap-4">
            <PaidDaysLeft :paid-days-left="paidDaysLeft" />
            <Button as-child>
                <Link :href="create()">Request Time Off</Link>
            </Button>
        </div>

        <section class="space-y-2">
            <h2 class="text-sm font-medium">Your Time Off</h2>
            <DataTable :columns="columns" :rows="timeOff">
                <template #cell-time_off="{ row }">
                    <TimeOffSummary :time-off="row" />
                </template>
                <template #cell-state="{ row }">
                    <div class="flex items-center justify-end gap-2">
                        <StatusBadge :state="row.state" />
                        <ConfirmDialog
                            v-if="row.can_cancel"
                            :form="
                                TimeOffCancellationController.store.form(row.id)
                            "
                            :title="`Cancel your Time Off for ${formatDateRange(row.first_date, row.last_date)}?`"
                            :description="
                                row.is_paid
                                    ? 'It stays on record as Cancelled, and its Paid Days are free to use again.'
                                    : 'It stays on record as Cancelled.'
                            "
                            confirm-label="Cancel Time Off"
                            dismiss-label="Keep it"
                        >
                            <template #trigger>
                                <Button variant="ghost" size="sm"
                                    >Cancel</Button
                                >
                            </template>
                        </ConfirmDialog>
                    </div>
                </template>
                <template #empty>
                    <EmptyState
                        :icon="CalendarDays"
                        message="Your Time Off requests appear here. You haven't requested any yet."
                    />
                </template>
            </DataTable>
        </section>
    </PageContainer>
</template>

<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { CalendarDays } from '@lucide/vue';
import TimeOffCancellationController from '@/actions/App/Http/Controllers/TimeOff/TimeOffCancellationController';
import DataTable from '@/components/DataTable.vue';
import EmptyState from '@/components/EmptyState.vue';
import Heading from '@/components/Heading.vue';
import PageContainer from '@/components/PageContainer.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import TimeOffSummary from '@/components/time-off/TimeOffSummary.vue';
import { Button } from '@/components/ui/button';
import { create, index } from '@/routes/time-off';
import type { TableColumn, TimeOffRecord } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Time Off', href: index() }],
    },
});

defineProps<{
    year: number;
    /** Paid Days minus Pending and Approved Paid Time Off this year. */
    paidDaysLeft: number;
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
            <p class="text-sm">
                <span
                    class="text-2xl font-semibold tabular-nums"
                    :class="{ 'text-destructive': paidDaysLeft < 0 }"
                    >{{ paidDaysLeft }}</span
                >
                <span class="ml-2 text-muted-foreground"
                    >Paid Days left in {{ year }}</span
                >
            </p>
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
                        <Form
                            v-if="row.can_cancel"
                            v-bind="
                                TimeOffCancellationController.store.form(row.id)
                            "
                            :options="{ preserveScroll: true }"
                            v-slot="{ processing }"
                        >
                            <SubmitButton
                                :processing="processing"
                                variant="ghost"
                                size="sm"
                                class="text-destructive"
                                >Cancel</SubmitButton
                            >
                        </Form>
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

<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { Plane } from '@lucide/vue';
import TravelRequestCancellationController from '@/actions/App/Http/Controllers/Travel/TravelRequestCancellationController';
import DataTable from '@/components/DataTable.vue';
import EmptyState from '@/components/EmptyState.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import TravelExpenseList from '@/components/travel-requests/TravelExpenseList.vue';
import { formatDate } from '@/lib/dates';
import { formatDays } from '@/lib/time-off';
import type { TableColumn, TravelEditing, TravelRequestRecord } from '@/types';

/**
 * An Employee's Travel Requests, each with its Travel Expenses, cancelling
 * it and recording or changing its Travel Expenses where `editing` is given
 * and its flags allow. Without `editing` the list is only to be seen, as on
 * the Employee page.
 */
defineProps<{
    travelRequests: TravelRequestRecord[];
    /** What the empty state says. */
    emptyMessage: string;
    editing?: TravelEditing;
}>();

const columns: TableColumn[] = [
    { key: 'travel', label: 'Travel Request' },
    { key: 'state', label: 'State', align: 'end' },
];
</script>

<template>
    <DataTable :columns="columns" :rows="travelRequests">
        <template #cell-travel="{ row }">
            <p>
                <span class="font-medium">{{ row.destination }}</span>
                <span class="text-muted-foreground">
                    · {{ formatDate(row.first_date) }} –
                    {{ formatDate(row.last_date) }} ·
                    {{ formatDays(row.days) }}</span
                >
                <span class="block text-muted-foreground"
                    >{{ row.project
                    }}<template v-if="row.client">
                        · {{ row.client }}</template
                    ></span
                >
                <span class="block">{{ row.purpose }}</span>
                <span
                    v-if="row.decided_by !== null"
                    class="block text-muted-foreground"
                    >{{ row.state === 'declined' ? 'Declined' : 'Approved' }} by
                    {{ row.decided_by
                    }}<template v-if="row.decline_reason !== null"
                        >: “{{ row.decline_reason }}”</template
                    ></span
                >
            </p>
            <TravelExpenseList
                v-if="row.expenses !== null"
                :editing="editing"
                :travel-request-id="row.id"
                :expenses="row.expenses"
            />
        </template>
        <template #cell-state="{ row }">
            <div class="flex items-center justify-end gap-2">
                <StatusBadge :state="row.state" />
                <Form
                    v-if="row.can_cancel"
                    v-bind="
                        TravelRequestCancellationController.store.form(row.id)
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
            <EmptyState :icon="Plane" :message="emptyMessage" />
        </template>
    </DataTable>
</template>

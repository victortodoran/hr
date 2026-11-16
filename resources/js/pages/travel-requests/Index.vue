<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { Plane } from '@lucide/vue';
import TravelRequestCancellationController from '@/actions/App/Http/Controllers/Travel/TravelRequestCancellationController';
import DataTable from '@/components/DataTable.vue';
import EmptyState from '@/components/EmptyState.vue';
import Heading from '@/components/Heading.vue';
import PageContainer from '@/components/PageContainer.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import RequestTravelDialog from '@/components/travel-requests/RequestTravelDialog.vue';
import TravelExpenseList from '@/components/travel-requests/TravelExpenseList.vue';
import { formatDate } from '@/lib/dates';
import { formatDays } from '@/lib/time-off';
import { index } from '@/routes/travel-requests';
import type {
    TableColumn,
    TravelAssignment,
    TravelCurrency,
    TravelRequestRecord,
} from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Travel', href: index() }],
    },
});

defineProps<{
    today: string;
    currency: TravelCurrency;
    travelRequests: TravelRequestRecord[];
    assignments: TravelAssignment[];
}>();

const columns: TableColumn[] = [
    { key: 'travel', label: 'Travel Request' },
    { key: 'state', label: 'State', align: 'end' },
];
</script>

<template>
    <Head title="Travel" />

    <PageContainer width="wide">
        <Heading
            title="Travel"
            description="Ask to travel for work on one of your Assignments, cancel it before it starts, and add what you paid for it once approved"
        />

        <div class="flex justify-end">
            <RequestTravelDialog :today="today" :assignments="assignments" />
        </div>

        <section class="space-y-2">
            <h2 class="text-sm font-medium">Your Travel Requests</h2>
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
                            >{{
                                row.state === 'declined'
                                    ? 'Declined'
                                    : 'Approved'
                            }}
                            by {{ row.decided_by
                            }}<template v-if="row.decline_reason !== null"
                                >: “{{ row.decline_reason }}”</template
                            ></span
                        >
                    </p>
                    <TravelExpenseList
                        v-if="row.expenses !== null"
                        :today="today"
                        :currency="currency"
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
                                TravelRequestCancellationController.store.form(
                                    row.id,
                                )
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
                        :icon="Plane"
                        message="Your Travel Requests appear here. You haven't made any yet."
                    />
                </template>
            </DataTable>
        </section>
    </PageContainer>
</template>

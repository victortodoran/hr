<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { Plane } from '@lucide/vue';
import TravelRequestApprovalController from '@/actions/App/Http/Controllers/Travel/TravelRequestApprovalController';
import TravelRequestDeclineController from '@/actions/App/Http/Controllers/Travel/TravelRequestDeclineController';
import DataTable from '@/components/DataTable.vue';
import EmptyState from '@/components/EmptyState.vue';
import InputError from '@/components/InputError.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import { Input } from '@/components/ui/input';
import { formatDate } from '@/lib/dates';
import { formatDays } from '@/lib/time-off';
import type { TableColumn, TravelRequestToApprove } from '@/types';

/**
 * The Travel Requests tab of the Approvals page: the Pending Travel Requests
 * of the Employees the viewer manages, each approved or declined with a
 * reason.
 */
defineProps<{
    toApprove: TravelRequestToApprove[];
}>();

const columns: TableColumn[] = [
    { key: 'employee', label: 'Employee' },
    { key: 'travel', label: 'Travel' },
    { key: 'answer', label: 'Answer' },
];
</script>

<template>
    <DataTable :columns="columns" :rows="toApprove">
        <template #cell-employee="{ row }">
            <span class="font-medium">{{ row.employee.name }}</span>
        </template>
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
            </p>
        </template>
        <template #cell-answer="{ row }">
            <div class="flex flex-wrap items-start gap-2">
                <Form
                    v-bind="TravelRequestApprovalController.store.form(row.id)"
                    :options="{ preserveScroll: true }"
                    v-slot="{ processing }"
                >
                    <SubmitButton :processing="processing" size="sm"
                        >Approve</SubmitButton
                    >
                </Form>
                <Form
                    v-bind="TravelRequestDeclineController.store.form(row.id)"
                    :options="{ preserveScroll: true }"
                    v-slot="{ errors, processing }"
                    class="flex flex-1 flex-wrap items-start gap-2"
                >
                    <div class="grid min-w-40 flex-1 gap-1">
                        <Input
                            :id="`travel-decline-reason-${row.id}`"
                            name="decline_reason"
                            aria-label="Reason for declining"
                            placeholder="Reason for declining"
                            required
                        />
                        <InputError :message="errors.decline_reason" />
                    </div>
                    <SubmitButton
                        :processing="processing"
                        variant="secondary"
                        size="sm"
                        class="text-destructive"
                        >Decline</SubmitButton
                    >
                </Form>
            </div>
        </template>
        <template #empty>
            <EmptyState
                :icon="Plane"
                message="Travel Requests from the Employees you manage appear here for you to approve or decline. None is waiting."
            />
        </template>
    </DataTable>
</template>

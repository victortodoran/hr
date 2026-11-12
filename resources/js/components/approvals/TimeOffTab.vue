<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { CalendarCheck } from '@lucide/vue';
import TimeOffApprovalController from '@/actions/App/Http/Controllers/TimeOffApprovalController';
import TimeOffDeclineController from '@/actions/App/Http/Controllers/TimeOffDeclineController';
import DataTable from '@/components/DataTable.vue';
import EmptyState from '@/components/EmptyState.vue';
import InputError from '@/components/InputError.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import { Input } from '@/components/ui/input';
import { formatDate } from '@/lib/dates';
import { formatDays } from '@/lib/time-off';
import type { TableColumn, TimeOffToApprove } from '@/types';

/**
 * The Time Off tab of the Approvals page: Direct Reports' Pending Time Off,
 * each approved or declined with a reason, and the Paid Days it leaves.
 */
defineProps<{
    toApprove: TimeOffToApprove[];
}>();

const columns: TableColumn[] = [
    { key: 'employee', label: 'Employee' },
    { key: 'dates', label: 'Dates' },
    { key: 'paid_days_left', label: 'Paid Days left', minor: true },
    { key: 'answer', label: 'Answer' },
];
</script>

<template>
    <DataTable :columns="columns" :rows="toApprove">
        <template #cell-employee="{ row }">
            <span class="font-medium">{{ row.employee.name }}</span>
        </template>
        <template #cell-dates="{ row }">
            {{ formatDate(row.first_date) }} – {{ formatDate(row.last_date) }}
            <span class="block text-xs text-muted-foreground"
                >{{ formatDays(row.days) }} ·
                {{ row.is_paid ? 'Paid' : 'Unpaid' }}</span
            >
        </template>
        <template #cell-paid_days_left="{ row }">
            <span
                v-for="(left, position) in row.paid_days_left"
                :key="left.year"
                class="whitespace-nowrap"
                ><template v-if="position > 0">, </template
                ><span
                    class="tabular-nums"
                    :class="{ 'text-destructive': left.days < 0 }"
                    >{{ left.days }}</span
                >
                in {{ left.year }}</span
            >
        </template>
        <template #cell-answer="{ row }">
            <div class="flex flex-wrap items-start gap-2">
                <Form
                    v-bind="TimeOffApprovalController.store.form(row.id)"
                    :options="{ preserveScroll: true }"
                    v-slot="{ errors, processing }"
                    class="space-y-1"
                >
                    <SubmitButton :processing="processing" size="sm"
                        >Approve</SubmitButton
                    >
                    <InputError :message="errors.approval" />
                </Form>
                <Form
                    v-bind="TimeOffDeclineController.store.form(row.id)"
                    :options="{ preserveScroll: true }"
                    v-slot="{ errors, processing }"
                    class="flex flex-1 flex-wrap items-start gap-2"
                >
                    <div class="grid min-w-40 flex-1 gap-1">
                        <Input
                            :id="`decline-reason-${row.id}`"
                            name="decline_reason"
                            aria-label="Reason for declining"
                            placeholder="Reason for declining"
                            required
                        />
                        <InputError :message="errors.decline_reason" />
                    </div>
                    <SubmitButton
                        :processing="processing"
                        variant="outline"
                        size="sm"
                        class="text-destructive"
                        >Decline</SubmitButton
                    >
                </Form>
            </div>
        </template>
        <template #empty>
            <EmptyState
                :icon="CalendarCheck"
                message="Time Off your Direct Reports request appears here for you to approve or decline. None is waiting."
            />
        </template>
    </DataTable>
</template>

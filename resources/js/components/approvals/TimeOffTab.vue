<script setup lang="ts">
import { CalendarCheck, Check, TriangleAlert, X } from '@lucide/vue';
import { computed } from 'vue';
import TimeOffApprovalController from '@/actions/App/Http/Controllers/TimeOff/TimeOffApprovalController';
import TimeOffDeclineController from '@/actions/App/Http/Controllers/TimeOff/TimeOffDeclineController';
import DataTable from '@/components/DataTable.vue';
import DecideDialog from '@/components/DecideDialog.vue';
import EmptyState from '@/components/EmptyState.vue';
import FactList from '@/components/FactList.vue';
import RecordSheet from '@/components/RecordSheet.vue';
import RowActions from '@/components/RowActions.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import { Button } from '@/components/ui/button';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { useApproveOne } from '@/composables/useApproveOne';
import { useRecordInUrl } from '@/composables/useRecordInUrl';
import { formatDate, formatDateRange } from '@/lib/dates';
import { formatDays } from '@/lib/time-off';
import type { TableColumn, TimeOffToApprove } from '@/types';

/**
 * The Time Off tab of the Approvals page: the Pending Time Off of the
 * Employees the viewer manages, each with the Paid Days it leaves, approved
 * or declined one at a time from its row or its RecordSheet.
 */
const props = defineProps<{
    toApprove: TimeOffToApprove[];
}>();

const columns: TableColumn[] = [
    { key: 'employee', label: 'Employee', phone: 'primary' },
    { key: 'dates', label: 'Dates', phone: 'secondary' },
    { key: 'kind', label: 'Days', minor: true, phone: 'end' },
    { key: 'paid_days_left', label: 'Paid Days left', minor: true },
    { key: 'actions', label: '', align: 'end', minor: true },
];

const { record, recordOpen, openRecord, closeRecord } = useRecordInUrl(
    () => props.toApprove,
);

const { approving, approveOne } = useApproveOne();

/** The Employee's other Pending Time Off, for the RecordSheet. */
const otherPending = computed(() =>
    record.value === null
        ? []
        : props.toApprove.filter(
              (timeOff) =>
                  timeOff.employee.id === record.value?.employee.id &&
                  timeOff.id !== record.value.id,
          ),
);

/** Whose Time Off a row is and when, naming its buttons. */
function describe(timeOff: TimeOffToApprove): string {
    return `${timeOff.employee.name}'s Time Off from ${formatDate(timeOff.first_date)}`;
}

/** "12 left in 2026, this request included" for each year it touches. */
function paidDaysLeft(timeOff: TimeOffToApprove): string {
    return timeOff.paid_days_left
        .map((left) => `${left.days} left in ${left.year}`)
        .join(', ');
}

function summaryOf(timeOff: TimeOffToApprove): string {
    return `${timeOff.employee.name} · ${formatDateRange(timeOff.first_date, timeOff.last_date)} · ${formatDays(timeOff.days)} ${timeOff.is_paid ? 'Paid' : 'Unpaid'}`;
}

function approve(timeOff: TimeOffToApprove): void {
    approveOne(
        timeOff.id,
        TimeOffApprovalController.store.url(timeOff.id),
        {},
        closeRecord,
    );
}

/** Whether a paid request takes a year below zero Paid Days. */
function overdraws(timeOff: TimeOffToApprove): boolean {
    return (
        timeOff.is_paid && timeOff.paid_days_left.some((left) => left.days < 0)
    );
}

const declineHelp =
    'It stays on record as Declined, and the Employee sees this reason on their Time Off page.';
const declinePlaceholder = 'Two of the team are already away that week';
</script>

<template>
    <EmptyState
        v-if="toApprove.length === 0"
        :icon="CalendarCheck"
        message="Time Off your Direct Reports request appears here for you to approve or decline. None is waiting."
    />

    <div v-else class="flex flex-col gap-5">
        <DataTable
            :columns="columns"
            :rows="toApprove"
            :on-row-click="(timeOff) => openRecord(timeOff.id)"
        >
            <template #cell-employee="{ row }">
                <span class="font-medium">{{ row.employee.name }}</span>
            </template>
            <template #cell-dates="{ row }">
                <span class="tabular-nums">{{
                    formatDateRange(row.first_date, row.last_date)
                }}</span>
            </template>
            <template #cell-kind="{ row }">
                <span class="whitespace-nowrap"
                    >{{ formatDays(row.days) }} ·
                    {{ row.is_paid ? 'Paid' : 'Unpaid' }}</span
                >
            </template>
            <template #cell-paid_days_left="{ row }">
                <span
                    v-if="row.is_paid"
                    class="tabular-nums"
                    :class="{
                        'text-status-attention-foreground': overdraws(row),
                    }"
                    ><TriangleAlert
                        v-if="overdraws(row)"
                        class="mr-1 inline size-4 align-text-bottom text-status-attention-icon"
                        aria-hidden="true"
                    />{{ paidDaysLeft(row) }},
                    <span class="text-muted-foreground"
                        >this request included</span
                    ></span
                >
                <span v-else class="text-muted-foreground">Unpaid</span>
            </template>
            <template #cell-actions="{ row }">
                <RowActions :label="describe(row)">
                    <Tooltip>
                        <TooltipTrigger as-child>
                            <Button
                                variant="ghost"
                                size="icon-sm"
                                :aria-label="`Approve ${describe(row)}`"
                                :disabled="approving !== null"
                                @click="approve(row)"
                                ><Check
                            /></Button>
                        </TooltipTrigger>
                        <TooltipContent>Approve</TooltipContent>
                    </Tooltip>
                    <Tooltip>
                        <DecideDialog
                            :action="TimeOffDeclineController.store(row.id)"
                            :noun="{ one: 'Time Off', many: 'Time Off' }"
                            :summary="summaryOf(row)"
                            :description="declineHelp"
                            :placeholder="declinePlaceholder"
                            @declined="closeRecord"
                        >
                            <template #trigger>
                                <TooltipTrigger as-child
                                    ><Button
                                        variant="ghost"
                                        size="icon-sm"
                                        :aria-label="`Decline ${describe(row)}`"
                                        ><X /></Button
                                ></TooltipTrigger>
                            </template>
                        </DecideDialog>
                        <TooltipContent>Decline…</TooltipContent>
                    </Tooltip>
                    <template #menu>
                        <DropdownMenuItem @select="openRecord(row.id)"
                            >Open</DropdownMenuItem
                        >
                    </template>
                </RowActions>
            </template>
        </DataTable>

        <RecordSheet
            v-if="record"
            v-model:open="recordOpen"
            :title="formatDateRange(record.first_date, record.last_date)"
            :description="`${record.employee.name}'s Time Off · Pending`"
        >
            <FactList
                :facts="[
                    { label: 'Employee', value: record.employee.name },
                    {
                        label: 'Days',
                        value: `${formatDays(record.days)} · ${record.is_paid ? 'Paid' : 'Unpaid'}`,
                    },
                    {
                        label: 'Paid Days left',
                        value: record.is_paid
                            ? `${paidDaysLeft(record)}, this request included`
                            : null,
                    },
                    {
                        label: 'Submitted',
                        value: record.submitted_on
                            ? formatDate(record.submitted_on)
                            : null,
                    },
                ]"
            />
            <section
                v-if="otherPending.length > 0"
                class="flex flex-col gap-1.5"
            >
                <h3 class="text-meta text-muted-foreground">
                    Other Pending Time Off
                </h3>
                <ul class="flex flex-col gap-1">
                    <li v-for="other in otherPending" :key="other.id">
                        {{ summaryOf(other) }}
                    </li>
                </ul>
            </section>
            <template #footer>
                <DecideDialog
                    :action="TimeOffDeclineController.store(record.id)"
                    :noun="{ one: 'Time Off', many: 'Time Off' }"
                    :summary="summaryOf(record)"
                    :description="declineHelp"
                    :placeholder="declinePlaceholder"
                    @declined="closeRecord"
                />
                <SubmitButton
                    type="button"
                    :processing="approving === record.id"
                    @click="approve(record)"
                    ><Check />Approve Time Off</SubmitButton
                >
            </template>
        </RecordSheet>
    </div>
</template>

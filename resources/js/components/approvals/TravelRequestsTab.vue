<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Check, Plane, X } from '@lucide/vue';
import { computed } from 'vue';
import TravelRequestApprovalController from '@/actions/App/Http/Controllers/Travel/TravelRequestApprovalController';
import TravelRequestDeclineController from '@/actions/App/Http/Controllers/Travel/TravelRequestDeclineController';
import DataTable from '@/components/DataTable.vue';
import DecideDialog from '@/components/DecideDialog.vue';
import EmptyState from '@/components/EmptyState.vue';
import FactList from '@/components/FactList.vue';
import RecordSheet from '@/components/RecordSheet.vue';
import RowActions from '@/components/RowActions.vue';
import { Button } from '@/components/ui/button';
import { DropdownMenuItem } from '@/components/ui/dropdown-menu';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { useRecordInUrl } from '@/composables/useRecordInUrl';
import { formatDate, formatDateRange } from '@/lib/dates';
import { formatDays } from '@/lib/time-off';
import type { TableColumn, TravelRequestToApprove } from '@/types';

/**
 * The Travel Requests tab of the Approvals page: the Pending Travel
 * Requests of the Employees the viewer manages, approved or declined one
 * at a time from its row or its RecordSheet.
 */
const props = defineProps<{
    toApprove: TravelRequestToApprove[];
}>();

const columns: TableColumn[] = [
    { key: 'employee', label: 'Employee', phone: 'secondary' },
    { key: 'destination', label: 'Destination', phone: 'primary' },
    { key: 'dates', label: 'Dates', phone: 'secondary' },
    { key: 'project', label: 'Project', minor: true },
    { key: 'purpose', label: 'Purpose', minor: true },
    { key: 'actions', label: '', align: 'end', minor: true },
];

const { recordId, openRecord, closeRecord } = useRecordInUrl();

const record = computed(
    () =>
        props.toApprove.find((travel) => travel.id === recordId.value) ?? null,
);

const recordOpen = computed({
    get: () => record.value !== null,
    set: (open: boolean) => {
        if (!open) {
            closeRecord();
        }
    },
});

/** Whose Travel Request a row is and where to, naming its buttons. */
function describe(travel: TravelRequestToApprove): string {
    return `${travel.employee.name}'s Travel Request to ${travel.destination}`;
}

function summaryOf(travel: TravelRequestToApprove): string {
    return `${travel.employee.name} · ${travel.destination} · ${formatDateRange(travel.first_date, travel.last_date)}`;
}

function approve(travel: TravelRequestToApprove): void {
    router.post(
        TravelRequestApprovalController.store.url(travel.id),
        {},
        { preserveScroll: true, onSuccess: () => closeRecord() },
    );
}

const declineHelp =
    'It stays on record as Declined, and the Employee sees this reason on their Travel page.';
const declinePlaceholder = 'The client meeting moved online';
</script>

<template>
    <EmptyState
        v-if="toApprove.length === 0"
        :icon="Plane"
        message="Travel Requests from the Employees you manage appear here for you to approve or decline. None is waiting."
    />

    <div v-else class="flex flex-col gap-5">
        <DataTable
            :columns="columns"
            :rows="toApprove"
            :on-row-click="(travel) => openRecord(travel.id)"
        >
            <template #cell-employee="{ row }">
                <span>{{ row.employee.name }}</span>
            </template>
            <template #cell-destination="{ row }">
                <span class="font-medium">{{ row.destination }}</span>
            </template>
            <template #cell-dates="{ row }">
                <span class="tabular-nums"
                    >{{ formatDateRange(row.first_date, row.last_date) }} ·
                    {{ formatDays(row.days) }}</span
                >
            </template>
            <template #cell-project="{ row }">
                {{ row.project
                }}<span v-if="row.client" class="text-muted-foreground">
                    · {{ row.client }}</span
                >
            </template>
            <template #cell-purpose="{ row }">
                <span class="line-clamp-2">{{ row.purpose }}</span>
            </template>
            <template #cell-actions="{ row }">
                <RowActions :label="describe(row)">
                    <Tooltip>
                        <TooltipTrigger as-child>
                            <Button
                                variant="ghost"
                                size="icon-sm"
                                :aria-label="`Approve ${describe(row)}`"
                                @click="approve(row)"
                                ><Check
                            /></Button>
                        </TooltipTrigger>
                        <TooltipContent>Approve</TooltipContent>
                    </Tooltip>
                    <Tooltip>
                        <DecideDialog
                            :action="
                                TravelRequestDeclineController.store(row.id)
                            "
                            :noun="{
                                one: 'Travel Request',
                                many: 'Travel Requests',
                            }"
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
            :title="record.destination"
            :description="`${record.employee.name}'s Travel Request · Pending`"
        >
            <FactList
                :facts="[
                    { label: 'Employee', value: record.employee.name },
                    {
                        label: 'Dates',
                        value: `${formatDateRange(record.first_date, record.last_date)} · ${formatDays(record.days)}`,
                    },
                    { label: 'Project', value: record.project },
                    { label: 'Client', value: record.client },
                    {
                        label: 'Submitted',
                        value: record.submitted_on
                            ? formatDate(record.submitted_on)
                            : null,
                    },
                ]"
            />
            <section class="flex flex-col gap-1.5">
                <h3 class="text-meta text-muted-foreground">Purpose</h3>
                <p class="whitespace-pre-line">{{ record.purpose }}</p>
            </section>
            <template #footer>
                <DecideDialog
                    :action="TravelRequestDeclineController.store(record.id)"
                    :noun="{ one: 'Travel Request', many: 'Travel Requests' }"
                    :summary="summaryOf(record)"
                    :description="declineHelp"
                    :placeholder="declinePlaceholder"
                    @declined="closeRecord"
                />
                <Button @click="approve(record)"
                    ><Check />Approve Travel Request</Button
                >
            </template>
        </RecordSheet>
    </div>
</template>

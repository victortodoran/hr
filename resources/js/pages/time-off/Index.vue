<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { CalendarDays, Plus } from '@lucide/vue';
import { computed, ref } from 'vue';
import TimeOffCancellationController from '@/actions/App/Http/Controllers/TimeOff/TimeOffCancellationController';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import DataTable from '@/components/DataTable.vue';
import EmptyState from '@/components/EmptyState.vue';
import PageContainer from '@/components/PageContainer.vue';
import PageHeader from '@/components/PageHeader.vue';
import RowActions from '@/components/RowActions.vue';
import ScopeSwitch from '@/components/ScopeSwitch.vue';
import StatStrip from '@/components/StatStrip.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import RequestTimeOffSheet from '@/components/time-off/RequestTimeOffSheet.vue';
import { Button } from '@/components/ui/button';
import { DropdownMenuItem } from '@/components/ui/dropdown-menu';
import { useOverlayRoute } from '@/composables/useOverlayRoute';
import { formatDateRange } from '@/lib/dates';
import { formatDays } from '@/lib/time-off';
import { create, index } from '@/routes/time-off';
import type { PaidDaysLeftInYear, TableColumn, TimeOffRecord } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Time Off', href: index() }],
    },
});

const props = defineProps<{
    manager: string | null;
    paidDaysLeft: PaidDaysLeftInYear[];
    timeOff: TimeOffRecord[];
    requesting?: boolean;
    today?: string;
}>();

const columns: TableColumn[] = [
    { key: 'dates', label: 'Dates', phone: 'primary' },
    { key: 'days', label: 'Days', phone: 'secondary' },
    { key: 'kind', label: 'Kind', phone: 'secondary' },
    { key: 'state', label: 'Status', phone: 'end' },
    { key: 'decided_by', label: 'Decided by', minor: true },
    { key: 'actions', label: '', align: 'end', phone: 'end' },
];

const description = computed(() =>
    props.manager
        ? `Your Time Off and the Paid Days you have left. ${props.manager} decides each request.`
        : 'Your Time Off and the Paid Days you have left. With no Manager, each request is Approved at once.',
);

const stats = computed(() =>
    props.paidDaysLeft.map((inYear) => ({
        label: `Paid Days left in ${inYear.year}`,
        value: String(inYear.days),
    })),
);

const { open: sheetOpen, setOpen: setSheetOpen } = useOverlayRoute(
    () => props.requesting === true,
    () => index().url,
);

const cancelling = ref<TimeOffRecord | null>(null);
const cancelOpen = ref(false);

function askToCancel(timeOff: TimeOffRecord): void {
    cancelling.value = timeOff;
    cancelOpen.value = true;
}

function cancelDescription(timeOff: TimeOffRecord): string {
    const dates = formatDateRange(timeOff.first_date, timeOff.last_date);

    return timeOff.is_paid
        ? `Your Time Off for ${dates} stays on record as Cancelled, and its ${timeOff.days === 1 ? 'Paid Day comes' : `${timeOff.days} Paid Days come`} back to you.`
        : `Your Time Off for ${dates} stays on record as Cancelled.`;
}
</script>

<template>
    <Head title="Time Off" />

    <PageContainer width="full">
        <PageHeader title="Time Off" :description="description">
            <template #actions>
                <Button as-child>
                    <Link :href="create()" preserve-scroll preserve-state
                        ><Plus />Request Time Off</Link
                    >
                </Button>
            </template>
        </PageHeader>
        <ScopeSwitch area="timeOff" />

        <StatStrip :stats="stats" />

        <DataTable :columns="columns" :rows="timeOff">
            <template #cell-dates="{ row }">
                <span class="tabular-nums">{{
                    formatDateRange(row.first_date, row.last_date)
                }}</span>
                <span
                    v-if="row.decline_reason"
                    class="block text-caption font-normal text-status-declined-foreground"
                    data-test="decline-reason"
                    >Declined<template v-if="row.decided_by">
                        by {{ row.decided_by }}</template
                    >: {{ row.decline_reason }}</span
                >
            </template>
            <template #cell-days="{ row }">
                <span class="whitespace-nowrap tabular-nums">{{
                    formatDays(row.days)
                }}</span>
            </template>
            <template #cell-kind="{ row }">
                {{ row.is_paid ? 'Paid' : 'Unpaid' }}
            </template>
            <template #cell-state="{ row }">
                <StatusBadge :state="row.state" />
            </template>
            <template #cell-decided_by="{ row }">
                {{ row.decided_by ?? '' }}
            </template>
            <template #cell-actions="{ row }">
                <RowActions
                    v-if="row.can_cancel"
                    :label="`your Time Off for ${formatDateRange(row.first_date, row.last_date)}`"
                >
                    <template #menu>
                        <DropdownMenuItem @select="askToCancel(row)"
                            >Cancel…</DropdownMenuItem
                        >
                    </template>
                </RowActions>
            </template>
            <template #empty>
                <EmptyState
                    :icon="CalendarDays"
                    message="Your Time Off requests appear here. You haven't requested any yet."
                />
            </template>
        </DataTable>

        <ConfirmDialog
            v-if="cancelling"
            v-model:open="cancelOpen"
            without-trigger
            :form="TimeOffCancellationController.store.form(cancelling.id)"
            title="Cancel Time Off"
            :description="cancelDescription(cancelling)"
            confirm-label="Cancel Time Off"
            dismiss-label="Keep it"
        />

        <RequestTimeOffSheet
            :open="sheetOpen"
            :today="today ?? ''"
            :paid-days-left="paidDaysLeft"
            @update:open="setSheetOpen"
        />
    </PageContainer>
</template>

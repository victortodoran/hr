<script setup lang="ts">
import { Receipt } from '@lucide/vue';
import TravelExpenseController from '@/actions/App/Http/Controllers/Travel/TravelExpenseController';
import TravelExpenseReceiptController from '@/actions/App/Http/Controllers/Travel/TravelExpenseReceiptController';
import ConfirmDeleteDialog from '@/components/ConfirmDeleteDialog.vue';
import DataTable from '@/components/DataTable.vue';
import EmptyState from '@/components/EmptyState.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import TravelExpenseDialog from '@/components/travel-requests/TravelExpenseDialog.vue';
import { Button } from '@/components/ui/button';
import { formatDate } from '@/lib/dates';
import { travelExpenseCategories } from '@/lib/travel-expenses';
import type { TableColumn, TravelEditing, TravelExpenses } from '@/types';

/**
 * The Travel Expenses of one Travel Request, with their total, adding one,
 * and changing or deleting each while it is Pending or Declined, where
 * `editing` is given and the flags allow. Without `editing` they are only to
 * be seen, as on the Employee page.
 */
defineProps<{
    editing?: TravelEditing;
    travelRequestId: number;
    expenses: TravelExpenses;
}>();

const columns: TableColumn[] = [
    { key: 'expense', label: 'Expense' },
    { key: 'paid_on', label: 'Paid', minor: true },
    { key: 'amount', label: 'Amount', align: 'end' },
    { key: 'state', label: 'State', align: 'end' },
];
</script>

<template>
    <div class="mt-3 space-y-2">
        <div class="flex items-center justify-between gap-2">
            <h3 class="text-sm font-medium">
                Expenses
                <span class="font-normal text-muted-foreground"
                    >· {{ expenses.total }}</span
                >
            </h3>
            <TravelExpenseDialog
                v-if="editing && expenses.can_create"
                :today="editing.today"
                :currency="editing.currency"
                :travel-request-id="travelRequestId"
            />
        </div>
        <DataTable :columns="columns" :rows="expenses.items">
            <template #cell-expense="{ row }">
                <p>
                    <span class="font-medium">{{
                        travelExpenseCategories[row.category]
                    }}</span>
                    <span class="text-muted-foreground sm:hidden">
                        · {{ formatDate(row.paid_on) }}</span
                    >
                    <span class="block">{{ row.description }}</span>
                    <a
                        :href="TravelExpenseReceiptController.show.url(row.id)"
                        target="_blank"
                        rel="noopener"
                        class="text-muted-foreground underline underline-offset-4"
                        >Receipt</a
                    >
                    <span
                        v-if="row.decline_reason !== null"
                        class="block text-muted-foreground"
                        >Declined: “{{ row.decline_reason }}”</span
                    >
                </p>
            </template>
            <template #cell-paid_on="{ row }">
                {{ formatDate(row.paid_on) }}
            </template>
            <template #cell-amount="{ row }">
                <span class="whitespace-nowrap tabular-nums">{{
                    row.amount
                }}</span>
            </template>
            <template #cell-state="{ row }">
                <div class="flex flex-wrap items-center justify-end gap-1">
                    <StatusBadge :state="row.state" />
                    <template v-if="editing && row.can_change">
                        <TravelExpenseDialog
                            :today="editing.today"
                            :currency="editing.currency"
                            :travel-request-id="travelRequestId"
                            :expense="row"
                        >
                            <template #trigger>
                                <Button variant="ghost" size="sm">Edit</Button>
                            </template>
                        </TravelExpenseDialog>
                        <ConfirmDeleteDialog
                            :form="TravelExpenseController.destroy.form(row.id)"
                            title="Delete this expense?"
                            description="The expense and its receipt are deleted for good."
                        />
                    </template>
                </div>
            </template>
            <template #empty>
                <EmptyState
                    :icon="Receipt"
                    :message="
                        editing
                            ? 'Money you paid yourself for this travel appears here, each with its receipt.'
                            : 'Money paid for this travel appears here, each with its receipt. None has been added yet.'
                    "
                />
            </template>
        </DataTable>
    </div>
</template>

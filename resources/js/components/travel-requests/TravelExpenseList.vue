<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Receipt } from '@lucide/vue';
import TravelExpenseController from '@/actions/App/Http/Controllers/Travel/TravelExpenseController';
import TravelExpenseReceiptController from '@/actions/App/Http/Controllers/Travel/TravelExpenseReceiptController';
import ConfirmDeleteDialog from '@/components/ConfirmDeleteDialog.vue';
import DataTable from '@/components/DataTable.vue';
import EmptyState from '@/components/EmptyState.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { formatDate } from '@/lib/dates';
import { travelExpenseCategories } from '@/lib/travel-expenses';
import { create, edit } from '@/routes/travel-requests/expenses';
import type { TableColumn, TravelExpenses } from '@/types';

/**
 * The Travel Expenses of one Travel Request on its page, with their total,
 * "Add expense" beside it, and Edit and Delete on each while the flags
 * allow, which they only do for the Employee themselves.
 */
defineProps<{
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
    <section class="flex flex-col gap-3">
        <div class="flex items-center justify-between gap-2">
            <h2 class="text-base font-medium">
                Expenses
                <span class="font-normal text-muted-foreground"
                    >· {{ expenses.total }}</span
                >
            </h2>
            <Button v-if="expenses.can_create" variant="outline" as-child>
                <Link :href="create(travelRequestId)">Add expense</Link>
            </Button>
        </div>
        <DataTable :columns="columns" :rows="expenses.items">
            <template #cell-expense="{ row }">
                <p>
                    <span class="font-medium">{{
                        travelExpenseCategories[row.category]
                    }}</span>
                    · {{ row.description }}
                    <span class="block text-muted-foreground sm:hidden">{{
                        formatDate(row.paid_on)
                    }}</span>
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
                    <template v-if="row.can_change">
                        <Button variant="ghost" size="sm" as-child>
                            <Link
                                :href="
                                    edit({
                                        travelRequest: travelRequestId,
                                        travelExpense: row.id,
                                    })
                                "
                                >Edit</Link
                            >
                        </Button>
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
                    message="Money paid for this travel appears here, each with its receipt. None has been added yet."
                />
            </template>
        </DataTable>
    </section>
</template>

<script setup lang="ts">
import { Receipt } from '@lucide/vue';
import { computed } from 'vue';
import TravelExpenseApprovalController from '@/actions/App/Http/Controllers/Travel/TravelExpenseApprovalController';
import TravelExpenseDeclineController from '@/actions/App/Http/Controllers/Travel/TravelExpenseDeclineController';
import TravelExpenseReceiptController from '@/actions/App/Http/Controllers/Travel/TravelExpenseReceiptController';
import DeclineSelectedDialog from '@/components/approvals/DeclineSelectedDialog.vue';
import DataTable from '@/components/DataTable.vue';
import EmptyState from '@/components/EmptyState.vue';
import SelectionBar from '@/components/SelectionBar.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import { Checkbox } from '@/components/ui/checkbox';
import { useBulkSelection } from '@/composables/useBulkSelection';
import { formatDate } from '@/lib/dates';
import { travelExpenseCategories } from '@/lib/travel-expenses';
import type { TableColumn, TravelExpensesToApprove } from '@/types';

/**
 * The Travel Expenses tab of the Approvals page: the Pending Travel Expenses
 * of the Employees the viewer manages, grouped by Travel Request, each with
 * its receipt, and approving or declining the selected ones.
 */
const props = defineProps<{
    toApprove: TravelExpensesToApprove[];
}>();

const columns: TableColumn[] = [
    { key: 'select', label: 'Select' },
    { key: 'paid_on', label: 'Paid', minor: true },
    { key: 'expense', label: 'Expense' },
    { key: 'amount', label: 'Amount', align: 'end' },
];

const allExpenses = computed(() =>
    props.toApprove.flatMap((travelRequest) => travelRequest.expenses),
);

const { selected, isSelected, toggle, clear, processing, submit } =
    useBulkSelection(() => allExpenses.value);

function isGroupSelected(travelRequest: TravelExpensesToApprove): boolean {
    return travelRequest.expenses.every((expense) => isSelected(expense.id));
}

function toggleGroup(
    travelRequest: TravelExpensesToApprove,
    checked: boolean,
): void {
    travelRequest.expenses.forEach((expense) => toggle(expense.id, checked));
}

function approve(): void {
    submit(TravelExpenseApprovalController.store.url());
}
</script>

<template>
    <div class="flex flex-col gap-6">
        <SelectionBar :count="selected.length" @clear="clear">
            <template #actions>
                <DeclineSelectedDialog
                    :ids="selected"
                    :action="TravelExpenseDeclineController.store()"
                    :noun="{ one: 'Travel Expense', many: 'Travel Expenses' }"
                    description="The Employee sees the reason under each one on their Travel page, and can change or delete it. Declined expenses aren't paid back."
                    placeholder="The receipt doesn't show the amount"
                    @declined="clear"
                />
                <SubmitButton
                    :processing="processing"
                    type="button"
                    :disabled="selected.length === 0"
                    @click="approve"
                    >Approve</SubmitButton
                >
            </template>
        </SelectionBar>

        <DataTable v-if="toApprove.length === 0" :columns="columns" :rows="[]">
            <template #empty>
                <EmptyState
                    :icon="Receipt"
                    message="Travel Expenses from the Employees you manage appear here, with their receipts, for you to approve or decline. None is waiting."
                />
            </template>
        </DataTable>

        <section
            v-for="travelRequest in toApprove"
            :key="travelRequest.id"
            class="flex flex-col gap-2"
        >
            <h3 class="text-sm">
                <span class="font-medium">{{
                    travelRequest.employee.name
                }}</span>
                · {{ travelRequest.destination }}
                <span class="text-muted-foreground">
                    · {{ formatDate(travelRequest.first_date) }} –
                    {{ formatDate(travelRequest.last_date) }}</span
                >
            </h3>
            <DataTable :columns="columns" :rows="travelRequest.expenses">
                <template #head-select>
                    <Checkbox
                        :model-value="isGroupSelected(travelRequest)"
                        :aria-label="`Select ${travelRequest.employee.name}'s expenses for ${travelRequest.destination}`"
                        @update:model-value="
                            toggleGroup(travelRequest, $event === true)
                        "
                    />
                </template>
                <template #cell-select="{ row }">
                    <Checkbox
                        :model-value="isSelected(row.id)"
                        :aria-label="`Select ${travelRequest.employee.name}'s ${travelExpenseCategories[row.category]} expense paid on ${formatDate(row.paid_on)}`"
                        @update:model-value="toggle(row.id, $event === true)"
                    />
                </template>
                <template #cell-paid_on="{ row }">
                    <span class="whitespace-nowrap">{{
                        formatDate(row.paid_on)
                    }}</span>
                </template>
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
                            :href="
                                TravelExpenseReceiptController.show.url(row.id)
                            "
                            target="_blank"
                            rel="noopener"
                            class="text-muted-foreground underline underline-offset-4"
                            >Receipt</a
                        >
                    </p>
                </template>
                <template #cell-amount="{ row }">
                    <span class="whitespace-nowrap tabular-nums">{{
                        row.amount
                    }}</span>
                </template>
            </DataTable>
        </section>
    </div>
</template>

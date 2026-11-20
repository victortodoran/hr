<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { Check, Receipt, X } from '@lucide/vue';
import { computed, ref } from 'vue';
import TravelExpenseApprovalController from '@/actions/App/Http/Controllers/Travel/TravelExpenseApprovalController';
import TravelExpenseController from '@/actions/App/Http/Controllers/Travel/TravelExpenseController';
import TravelExpenseDeclineController from '@/actions/App/Http/Controllers/Travel/TravelExpenseDeclineController';
import TravelExpenseReceiptController from '@/actions/App/Http/Controllers/Travel/TravelExpenseReceiptController';
import TravelExpenseReopeningController from '@/actions/App/Http/Controllers/Travel/TravelExpenseReopeningController';
import Callout from '@/components/Callout.vue';
import ConfirmDeleteDialog from '@/components/ConfirmDeleteDialog.vue';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import DataTable from '@/components/DataTable.vue';
import DecideDialog from '@/components/DecideDialog.vue';
import EmptyState from '@/components/EmptyState.vue';
import IconButton from '@/components/IconButton.vue';
import RowActions from '@/components/RowActions.vue';
import SelectionBar from '@/components/SelectionBar.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    DropdownMenuItem,
    DropdownMenuSeparator,
} from '@/components/ui/dropdown-menu';
import { useApproveOne } from '@/composables/useApproveOne';
import { useBulkSelection } from '@/composables/useBulkSelection';
import { formatDate } from '@/lib/dates';
import { travelExpenseCategories } from '@/lib/travel-expenses';
import { edit } from '@/routes/travel-requests/expenses';
import type { TableColumn, TravelExpenseRecord, TravelExpenses } from '@/types';

const props = defineProps<{
    travelRequestId: number;
    expenses: TravelExpenses;
}>();

const reopenable = computed(() =>
    props.expenses.items.filter((expense) => expense.can_reopen),
);

const canReopen = computed(() => reopenable.value.length > 0);

const { selected, isSelected, toggle, clear } = useBulkSelection(
    () => reopenable.value,
);

const { approving, approveOne } = useApproveOne();

const selectedNoun = computed(() =>
    selected.value.length === 1
        ? '1 Travel Expense'
        : `${selected.value.length} Travel Expenses`,
);

const columns = computed<TableColumn[]>(() => [
    ...(canReopen.value ? [{ key: 'select', label: 'Select' }] : []),
    { key: 'paid_on', label: 'Date paid', phone: 'secondary' },
    { key: 'category', label: 'Category', phone: 'primary' },
    { key: 'description', label: 'Description', phone: 'secondary' },
    { key: 'amount', label: 'Amount', align: 'end', phone: 'end' },
    { key: 'state', label: 'Status', phone: 'end' },
    { key: 'receipt', label: 'Receipt', phone: 'end' },
    { key: 'actions', label: '', align: 'end', phone: 'end' },
]);

function describe(expense: TravelExpenseRecord): string {
    return `the ${travelExpenseCategories[expense.category]} expense paid on ${formatDate(expense.paid_on)}`;
}

function hasActions(expense: TravelExpenseRecord): boolean {
    return expense.can_decide || expense.can_change;
}

function editUrl(expense: TravelExpenseRecord): string {
    return edit.url({
        travelRequest: props.travelRequestId,
        travelExpense: expense.id,
    });
}

function openEdit(expense: TravelExpenseRecord): void {
    router.get(
        editUrl(expense),
        {},
        { preserveScroll: true, preserveState: true },
    );
}

function approve(expense: TravelExpenseRecord): void {
    approveOne(expense.id, TravelExpenseApprovalController.store.url(), {
        ids: [expense.id],
    });
}

const deleting = ref<TravelExpenseRecord | null>(null);
const deleteOpen = ref(false);

function askToDelete(expense: TravelExpenseRecord): void {
    deleting.value = expense;
    deleteOpen.value = true;
}
</script>

<template>
    <section class="flex min-w-0 flex-col gap-3">
        <h2 class="text-section">Travel Expenses</h2>
        <SelectionBar v-if="canReopen" :count="selected.length" @clear="clear">
            <template #actions>
                <ConfirmDialog
                    :form="
                        TravelExpenseReopeningController.store.form(
                            travelRequestId,
                        )
                    "
                    :title="`Reopen ${selectedNoun}?`"
                    :description="
                        selected.length === 1
                            ? 'It goes back to Pending so it can be corrected.'
                            : 'They go back to Pending so they can be corrected.'
                    "
                    :confirm-label="`Reopen ${selectedNoun}`"
                    @success="clear"
                >
                    <input
                        v-for="id in selected"
                        :key="id"
                        type="hidden"
                        name="ids[]"
                        :value="id"
                    />
                    <template #trigger>
                        <Button
                            variant="secondary"
                            :disabled="selected.length === 0"
                            >{{
                                selected.length === 0
                                    ? 'Reopen…'
                                    : `Reopen ${selectedNoun}…`
                            }}</Button
                        >
                    </template>
                </ConfirmDialog>
            </template>
        </SelectionBar>
        <DataTable
            :columns="columns"
            :rows="expenses.items"
            :has-after-row="(row) => row.state === 'declined'"
        >
            <template #head-select>
                <span class="sr-only">Select</span>
            </template>
            <template #cell-select="{ row }">
                <Checkbox
                    v-if="row.can_reopen"
                    :model-value="isSelected(row.id)"
                    :aria-label="`Select ${describe(row)}`"
                    @update:model-value="toggle(row.id, $event === true)"
                />
            </template>
            <template #cell-paid_on="{ row }">
                <span class="whitespace-nowrap tabular-nums">{{
                    formatDate(row.paid_on)
                }}</span>
            </template>
            <template #cell-category="{ row }">
                {{ travelExpenseCategories[row.category] }}
            </template>
            <template #cell-description="{ row }">
                <span
                    :class="
                        row.state === 'declined' ? 'text-muted-foreground' : ''
                    "
                    >{{ row.description }}</span
                >
            </template>
            <template #cell-amount="{ row }">
                <span
                    class="whitespace-nowrap tabular-nums"
                    :class="
                        row.state === 'declined'
                            ? 'text-muted-foreground line-through'
                            : 'font-medium'
                    "
                    >{{ row.amount }}</span
                >
            </template>
            <template #cell-state="{ row }">
                <StatusBadge :state="row.state" />
            </template>
            <template #head-receipt>Receipt</template>
            <template #cell-receipt="{ row }">
                <a
                    :href="TravelExpenseReceiptController.show.url(row.id)"
                    target="_blank"
                    rel="noopener"
                    class="inline-flex items-center justify-center rounded-sm focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none max-sm:min-h-11 max-sm:min-w-11"
                    :aria-label="`Receipt for ${describe(row)}`"
                >
                    <img
                        v-if="row.receipt_is_image"
                        :src="TravelExpenseReceiptController.show.url(row.id)"
                        alt=""
                        loading="lazy"
                        class="h-10 w-8 rounded-sm border object-cover"
                    />
                    <span
                        v-else
                        class="flex h-10 w-8 items-center justify-center rounded-sm border bg-surface text-caption font-semibold text-muted-foreground"
                        >PDF</span
                    >
                </a>
            </template>
            <template #head-actions>
                <span class="sr-only">Actions</span>
            </template>
            <template #cell-actions="{ row }">
                <RowActions v-if="hasActions(row)" :label="describe(row)">
                    <template v-if="row.can_decide">
                        <IconButton
                            :label="`Approve ${describe(row)}`"
                            :disabled="approving !== null"
                            @click="approve(row)"
                            ><Check
                        /></IconButton>
                        <DecideDialog
                            :ids="[row.id]"
                            :action="TravelExpenseDeclineController.store()"
                            :noun="{
                                one: 'Travel Expense',
                                many: 'Travel Expenses',
                            }"
                            :summary="`${travelExpenseCategories[row.category]} · ${row.description} · ${row.amount}`"
                            description="The Employee sees the reason under it on this page, and can edit or delete it. A Declined Travel Expense isn't paid back."
                            placeholder="The receipt doesn't show the amount"
                        >
                            <template #trigger>
                                <IconButton :label="`Decline ${describe(row)}…`"
                                    ><X
                                /></IconButton>
                            </template>
                        </DecideDialog>
                    </template>
                    <template v-if="row.can_change" #menu>
                        <DropdownMenuItem @select="openEdit(row)"
                            >Edit</DropdownMenuItem
                        >
                        <DropdownMenuSeparator />
                        <DropdownMenuItem
                            variant="destructive"
                            @select="askToDelete(row)"
                            >Delete</DropdownMenuItem
                        >
                    </template>
                </RowActions>
            </template>
            <template #after-row="{ row }">
                <Callout tone="declined">
                    <span class="font-semibold"
                        >Declined<template v-if="row.declined_by">
                            by {{ row.declined_by }}</template
                        >:</span
                    >
                    “{{ row.decline_reason }}”
                    <template v-if="row.can_change" #action>
                        <Button variant="secondary" size="sm" as-child>
                            <Link
                                :href="editUrl(row)"
                                preserve-scroll
                                preserve-state
                                :aria-label="`Edit ${describe(row)}`"
                                >Edit</Link
                            >
                        </Button>
                    </template>
                </Callout>
            </template>
            <template #empty>
                <EmptyState
                    :icon="Receipt"
                    message="Money paid for this travel appears here, each with its receipt. None has been added yet."
                />
            </template>
        </DataTable>

        <ConfirmDeleteDialog
            v-if="deleting"
            v-model:open="deleteOpen"
            without-trigger
            :form="TravelExpenseController.destroy.form(deleting.id)"
            title="Delete Travel Expense"
            :description="`Your ${travelExpenseCategories[deleting.category]} expense of ${deleting.amount}, paid on ${formatDate(deleting.paid_on)}, and its receipt are deleted for good.`"
            confirm-label="Delete Travel Expense"
        />
    </section>
</template>

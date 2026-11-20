<script setup lang="ts">
import { Check, Receipt, X } from '@lucide/vue';
import { computed } from 'vue';
import TravelExpenseApprovalController from '@/actions/App/Http/Controllers/Travel/TravelExpenseApprovalController';
import TravelExpenseDeclineController from '@/actions/App/Http/Controllers/Travel/TravelExpenseDeclineController';
import TravelExpenseReceiptController from '@/actions/App/Http/Controllers/Travel/TravelExpenseReceiptController';
import DataTable from '@/components/DataTable.vue';
import DecideDialog from '@/components/DecideDialog.vue';
import EmptyState from '@/components/EmptyState.vue';
import FactList from '@/components/FactList.vue';
import RecordSheet from '@/components/RecordSheet.vue';
import RowActions from '@/components/RowActions.vue';
import SelectionBar from '@/components/SelectionBar.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import { Button } from '@/components/ui/button';
import { DropdownMenuItem } from '@/components/ui/dropdown-menu';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { useBulkSelection } from '@/composables/useBulkSelection';
import { useApproveOne } from '@/composables/useApproveOne';
import { useRecordInUrl } from '@/composables/useRecordInUrl';
import { formatDate, formatDateRange } from '@/lib/dates';
import {
    formatMinorUnits,
    travelExpenseCategories,
} from '@/lib/travel-expenses';
import type { TableColumn, TravelExpensesToApprove } from '@/types';

const props = defineProps<{
    toApprove: TravelExpensesToApprove[];
    currency: string;
}>();

type Expense = TravelExpensesToApprove['expenses'][number] & {
    travelRequest: TravelExpensesToApprove;
};

const columns: TableColumn[] = [
    { key: 'paid_on', label: 'Paid', phone: 'secondary' },
    { key: 'category', label: 'Category', phone: 'secondary' },
    { key: 'description', label: 'Description', phone: 'primary' },
    { key: 'amount', label: 'Amount', align: 'end', phone: 'end' },
    { key: 'actions', label: '', align: 'end', minor: true },
];

const expenses = computed<Expense[]>(() =>
    props.toApprove.flatMap((travelRequest) =>
        travelRequest.expenses.map((expense) => ({
            ...expense,
            travelRequest,
        })),
    ),
);

const selection = useBulkSelection(() => expenses.value);
const { selected, clear, processing, submit } = selection;

const { record, recordOpen, openRecord, closeRecord } = useRecordInUrl(
    () => expenses.value,
);

const { approving, approveOne: approve } = useApproveOne();

const otherPending = computed(() =>
    record.value === null
        ? []
        : record.value.travelRequest.expenses.filter(
              (expense) => expense.id !== record.value?.id,
          ),
);

const travelRequestOf = (id: string): TravelExpensesToApprove | undefined =>
    props.toApprove.find((travelRequest) => String(travelRequest.id) === id);

function groupName(id: string): string {
    const travelRequest = travelRequestOf(id);

    return `${travelRequest?.employee.name}'s Travel Expenses for ${travelRequest?.destination}`;
}

function describe(expense: Expense): string {
    return `${expense.travelRequest.employee.name}'s ${travelExpenseCategories[expense.category]} expense paid on ${formatDate(expense.paid_on)}`;
}

function summaryOf(chosen: Expense[]): string {
    const travelRequests = [
        ...new Set(chosen.map((expense) => expense.travelRequest)),
    ];

    return travelRequests
        .map(
            (travelRequest) =>
                `${travelRequest.employee.name} · ${travelRequest.destination}`,
        )
        .join('; ');
}

const selectedExpenses = computed(() =>
    expenses.value.filter((expense) => selection.isSelected(expense.id)),
);

const selectedTotal = computed(() =>
    formatMinorUnits(
        selectedExpenses.value.reduce(
            (sum, expense) => sum + expense.amount_minor,
            0,
        ),
        props.currency,
    ),
);

function approveSelected(): void {
    submit(TravelExpenseApprovalController.store.url());
}

function approveOne(expense: Expense): void {
    approve(
        expense.id,
        TravelExpenseApprovalController.store.url(),
        { ids: [expense.id] },
        () => declinedOrApproved(expense),
    );
}

function declinedOrApproved(expense: Expense): void {
    selection.toggle(expense.id, false);
    closeRecord();
}

const noun = { one: 'Travel Expense', many: 'Travel Expenses' };
const declineHelp =
    "The Employee sees this reason on their Travel page, and can edit or delete it. A declined expense isn't paid back.";
const declinePlaceholder = "The receipt doesn't show the amount";
</script>

<template>
    <EmptyState
        v-if="toApprove.length === 0"
        :icon="Receipt"
        message="Travel Expenses from the Employees you manage appear here, with their receipts, for you to approve or decline. None is waiting."
    />

    <div v-else class="flex flex-col gap-5">
        <DataTable
            :columns="columns"
            :rows="expenses"
            :group-by="(expense) => String(expense.travelRequest.id)"
            :selection="selection"
            :row-label="describe"
            :group-label="groupName"
            :on-row-click="(expense) => openRecord(expense.id)"
        >
            <template #group="{ group }">
                <span class="font-semibold">{{
                    travelRequestOf(group)?.employee.name
                }}</span>
                <span class="text-muted-foreground">
                    · {{ travelRequestOf(group)?.destination }} ·
                    {{
                        formatDateRange(
                            travelRequestOf(group)?.first_date ?? '',
                            travelRequestOf(group)?.last_date ?? '',
                        )
                    }}</span
                >
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
                <span class="line-clamp-2">{{ row.description }}</span>
            </template>
            <template #cell-amount="{ row }">
                <span class="whitespace-nowrap tabular-nums">{{
                    row.amount
                }}</span>
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
                                @click="approveOne(row)"
                                ><Check
                            /></Button>
                        </TooltipTrigger>
                        <TooltipContent>Approve</TooltipContent>
                    </Tooltip>
                    <Tooltip>
                        <DecideDialog
                            :ids="[row.id]"
                            :action="TravelExpenseDeclineController.store()"
                            :noun="noun"
                            :summary="`${summaryOf([row])} · ${row.amount}`"
                            :description="declineHelp"
                            :placeholder="declinePlaceholder"
                            @declined="declinedOrApproved(row)"
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
                        <DropdownMenuItem as-child>
                            <a
                                :href="
                                    TravelExpenseReceiptController.show.url(
                                        row.id,
                                    )
                                "
                                target="_blank"
                                rel="noopener"
                                >Open receipt</a
                            >
                        </DropdownMenuItem>
                    </template>
                </RowActions>
            </template>
        </DataTable>

        <SelectionBar
            :count="selected.length"
            :total="selectedTotal"
            @clear="clear"
        >
            <template #actions>
                <DecideDialog
                    :ids="selected"
                    :action="TravelExpenseDeclineController.store()"
                    :noun="noun"
                    :summary="summaryOf(selectedExpenses)"
                    :description="declineHelp"
                    :placeholder="declinePlaceholder"
                    size="sm"
                    @declined="clear"
                />
                <SubmitButton
                    :processing="processing"
                    type="button"
                    size="sm"
                    @click="approveSelected"
                    ><Check />Approve {{ selected.length
                    }}<span class="max-sm:hidden">
                        {{ selected.length === 1 ? noun.one : noun.many }}</span
                    ></SubmitButton
                >
            </template>
        </SelectionBar>

        <RecordSheet
            v-if="record"
            v-model:open="recordOpen"
            :title="`${travelExpenseCategories[record.category]} · ${record.amount}`"
            :description="`${record.travelRequest.employee.name}'s Travel Expense for ${record.travelRequest.destination} · Pending`"
        >
            <FactList
                :facts="[
                    {
                        label: 'Employee',
                        value: record.travelRequest.employee.name,
                    },
                    {
                        label: 'Travel',
                        value: `${record.travelRequest.destination} · ${formatDateRange(record.travelRequest.first_date, record.travelRequest.last_date)}`,
                    },
                    { label: 'Paid', value: formatDate(record.paid_on) },
                    { label: 'Amount', value: record.amount },
                    {
                        label: 'Submitted',
                        value: record.submitted_on
                            ? formatDate(record.submitted_on)
                            : null,
                    },
                ]"
            />
            <section class="flex flex-col gap-1.5">
                <h3 class="text-meta text-muted-foreground">Description</h3>
                <p class="whitespace-pre-line">{{ record.description }}</p>
            </section>
            <section
                v-if="otherPending.length > 0"
                class="flex flex-col gap-1.5"
            >
                <h3 class="text-meta text-muted-foreground">
                    Other Pending Travel Expenses on this Travel Request
                </h3>
                <ul class="flex flex-col gap-1">
                    <li v-for="other in otherPending" :key="other.id">
                        {{ formatDate(other.paid_on) }} ·
                        {{ travelExpenseCategories[other.category] }} ·
                        {{ other.amount }}
                    </li>
                </ul>
            </section>
            <section class="flex flex-col gap-1.5">
                <h3 class="text-meta text-muted-foreground">Receipt</h3>
                <img
                    v-if="record.receipt_is_image"
                    :src="TravelExpenseReceiptController.show.url(record.id)"
                    :alt="`Receipt for ${describe(record)}`"
                    class="max-h-80 w-full rounded-md border object-contain"
                />
                <a
                    :href="TravelExpenseReceiptController.show.url(record.id)"
                    target="_blank"
                    rel="noopener"
                    class="underline underline-offset-4"
                    >Open receipt</a
                >
            </section>
            <template #footer>
                <DecideDialog
                    :ids="[record.id]"
                    :action="TravelExpenseDeclineController.store()"
                    :noun="noun"
                    :summary="`${summaryOf([record])} · ${record.amount}`"
                    :description="declineHelp"
                    :placeholder="declinePlaceholder"
                    @declined="declinedOrApproved(record)"
                />
                <SubmitButton
                    type="button"
                    :processing="approving === record.id"
                    @click="approveOne(record)"
                    ><Check />Approve Travel Expense</SubmitButton
                >
            </template>
        </RecordSheet>
    </div>
</template>

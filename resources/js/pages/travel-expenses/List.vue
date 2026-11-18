<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Receipt } from '@lucide/vue';
import { computed } from 'vue';
import TravelExpenseReceiptController from '@/actions/App/Http/Controllers/Travel/TravelExpenseReceiptController';
import TravelExpenseReopeningController from '@/actions/App/Http/Controllers/Travel/TravelExpenseReopeningController';
import DataTable from '@/components/DataTable.vue';
import EmptyState from '@/components/EmptyState.vue';
import Heading from '@/components/Heading.vue';
import PageContainer from '@/components/PageContainer.vue';
import Pagination from '@/components/Pagination.vue';
import SelectionBar from '@/components/SelectionBar.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import { Checkbox } from '@/components/ui/checkbox';
import WorkEntryListFilterBar from '@/components/work-entries/WorkEntryListFilterBar.vue';
import { useBulkSelection } from '@/composables/useBulkSelection';
import { formatDate } from '@/lib/dates';
import { travelExpenseCategories } from '@/lib/travel-expenses';
import { index } from '@/routes/travel-expense-list';
import type {
    Choice,
    EmployeeWithWorkEntries,
    ListedTravelExpense,
    Paginated,
    TableColumn,
    TravelExpenseListFilters,
} from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Travel Expenses', href: index() }],
    },
});

/**
 * Travel Expenses of the Employees the viewer may see, a Period and an
 * Employee at a time, or every Employee at once, each with its receipt,
 * with reopening the selected ones. Approving happens on Approvals.
 */
const props = defineProps<{
    filters: TravelExpenseListFilters;
    today: string;
    canReopen: boolean;
    expenses: Paginated<ListedTravelExpense>;
    /** The total of the matching Travel Expenses that aren't Declined, in the company currency. */
    total: string;
    /** Every matching Travel Expense's ID; only sent when asked for. */
    matchingIds?: number[];
    /** Who has Travel Expenses paid in the Period, by name, with how many. */
    employees: EmployeeWithWorkEntries[];
    /** The Projects the Employee shown, or anyone, has Travel Expenses on in the Period. */
    projects: Choice[];
}>();

const columns = computed<TableColumn[]>(() => [
    ...(props.canReopen ? [{ key: 'select', label: 'Select' }] : []),
    { key: 'paid_on', label: 'Paid', minor: true },
    { key: 'employee', label: 'Employee' },
    { key: 'expense', label: 'Expense' },
    { key: 'project', label: 'Project', minor: true },
    { key: 'state', label: 'Status' },
    { key: 'amount', label: 'Amount', align: 'end' },
]);

function reloadList(query: Record<string, string>): void {
    clear();
    router.get(
        index.url({ query }),
        {},
        { preserveState: true, replace: true },
    );
}

const selectableOnPage = computed(() =>
    props.expenses.data.filter((expense) => expense.can_reopen),
);

const {
    selected,
    isPageSelected,
    isSelected,
    toggle,
    togglePage,
    selectAllMatching,
    clear,
    submit,
    processing,
} = useBulkSelection(() => selectableOnPage.value, {
    prop: 'matchingIds',
    ids: () => props.matchingIds,
});

/** Selecting every match is only useful when they are all Approved, the state the viewer can act on. */
const canSelectAllMatching = computed(
    () =>
        props.expenses.total > 0 &&
        props.filters.state === 'approved' &&
        props.canReopen,
);

function reopen(): void {
    submit(TravelExpenseReopeningController.store.url());
}

/**
 * Who approved a Travel Expense and when, such as "Approved by Ben on
 * 10 Nov 2026", or only when for an Employee with no Manager.
 */
function approvalNote(
    approved: NonNullable<ListedTravelExpense['approved']>,
): string {
    return approved.by
        ? `Approved by ${approved.by} on ${formatDate(approved.on)}`
        : `Approved on ${formatDate(approved.on)}`;
}
</script>

<template>
    <Head title="Travel Expenses" />

    <PageContainer width="wide">
        <Heading
            title="Travel Expenses"
            :description="
                canReopen
                    ? 'Money Employees paid for travel, to check and reopen'
                    : 'Money Employees paid for travel'
            "
        />

        <WorkEntryListFilterBar
            :filters="filters"
            :today="today"
            :employees="employees"
            :projects="projects"
            @change="reloadList"
        />

        <p v-if="expenses.total > 0" class="text-sm text-muted-foreground">
            <span class="font-medium text-foreground tabular-nums">{{
                total
            }}</span>
            in {{ expenses.total }} matching
            {{ expenses.total === 1 ? 'Travel Expense' : 'Travel Expenses' }},
            Declined ones left out
        </p>

        <div class="flex flex-col gap-3">
            <SelectionBar
                v-if="canReopen"
                :count="selected.length"
                :matching="canSelectAllMatching ? expenses.total : undefined"
                @select-all="selectAllMatching"
                @clear="clear"
            >
                <template #actions>
                    <SubmitButton
                        variant="outline"
                        :processing="processing"
                        type="button"
                        :disabled="selected.length === 0"
                        @click="reopen"
                        >Reopen</SubmitButton
                    >
                </template>
            </SelectionBar>

            <DataTable :columns="columns" :rows="expenses.data">
                <template #head-select>
                    <Checkbox
                        v-if="selectableOnPage.length > 0"
                        :model-value="isPageSelected"
                        aria-label="Select this page"
                        @update:model-value="togglePage($event === true)"
                    />
                </template>
                <template #cell-select="{ row }">
                    <Checkbox
                        v-if="row.can_reopen"
                        :model-value="isSelected(row.id)"
                        :aria-label="`Select ${row.employee.name}'s ${travelExpenseCategories[row.category]} expense paid on ${formatDate(row.paid_on)}`"
                        @update:model-value="toggle(row.id, $event === true)"
                    />
                </template>
                <template #cell-paid_on="{ row }">
                    <span class="whitespace-nowrap">{{
                        formatDate(row.paid_on)
                    }}</span>
                </template>
                <template #cell-employee="{ row }">
                    {{ row.employee.name }}
                </template>
                <template #cell-expense="{ row }">
                    <p data-test="travel-expense">
                        <span class="font-medium">{{
                            travelExpenseCategories[row.category]
                        }}</span>
                        <span class="text-muted-foreground">
                            · {{ row.destination }}</span
                        >
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
                        <span
                            v-if="row.approved"
                            class="block text-xs text-muted-foreground"
                            >{{ approvalNote(row.approved) }}</span
                        >
                        <span
                            v-if="row.decline_reason"
                            class="block text-xs text-status-declined-foreground"
                            >Declined: {{ row.decline_reason }}</span
                        >
                    </p>
                </template>
                <template #cell-state="{ row }">
                    <StatusBadge :state="row.state" />
                </template>
                <template #cell-amount="{ row }">
                    <span class="whitespace-nowrap tabular-nums">{{
                        row.amount
                    }}</span>
                </template>
                <template #empty>
                    <EmptyState
                        :icon="Receipt"
                        :message="
                            employees.length > 0
                                ? 'No Travel Expenses match these filters.'
                                : 'Travel Expenses paid in this period appear here, with their receipts. There are none.'
                        "
                    />
                </template>
            </DataTable>

            <Pagination :page="expenses" preserve-state />
        </div>
    </PageContainer>
</template>

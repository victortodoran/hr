<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { CheckCheck } from '@lucide/vue';
import ApprovalController from '@/actions/App/Http/Controllers/WorkEntries/ApprovalController';
import WorkEntryDeclineController from '@/actions/App/Http/Controllers/WorkEntries/WorkEntryDeclineController';
import ApprovalFilterBar from '@/components/approvals/ApprovalFilterBar.vue';
import DeclineSelectedDialog from '@/components/approvals/DeclineSelectedDialog.vue';
import DataTable from '@/components/DataTable.vue';
import EmptyState from '@/components/EmptyState.vue';
import Pagination from '@/components/Pagination.vue';
import SelectionBar from '@/components/SelectionBar.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import { Checkbox } from '@/components/ui/checkbox';
import { useBulkSelection } from '@/composables/useBulkSelection';
import { reloadList } from '@/composables/useListFilters';
import { formatDate } from '@/lib/dates';
import { formatHours } from '@/lib/work-entries';
import { index } from '@/routes/approvals';
import type {
    ApprovalEntry,
    ApprovalFilters,
    ChoiceToApprove,
    Paginated,
    TableColumn,
} from '@/types';

/**
 * The Work Entries tab of the Approvals page: the Pending Work Entries
 * waiting for the viewer's approval, one Employee at a time, each approved
 * or declined from its row, or the selected ones together. With nothing
 * waiting there is no Employee to choose.
 */
const props = defineProps<{
    filters: ApprovalFilters;
    entries: Paginated<ApprovalEntry>;
    /** Total hours of every Work Entry matching the filters. */
    total: number;
    /** Every matching Work Entry's ID; only sent when asked for. */
    matchingIds?: number[];
    employees: ChoiceToApprove[];
    projects: ChoiceToApprove[];
}>();

const columns: TableColumn[] = [
    { key: 'select', label: 'Select' },
    { key: 'date', label: 'Date' },
    { key: 'employee', label: 'Employee', minor: true },
    { key: 'project', label: 'Project', minor: true },
    { key: 'description', label: 'Description', minor: true },
    { key: 'hours', label: 'Hours', align: 'end', minor: true },
    { key: 'answer', label: 'Answer', align: 'end' },
];

const {
    selected,
    isPageSelected,
    isSelected,
    toggle,
    togglePage,
    selectAllMatching,
    clear,
    processing,
    submit,
} = useBulkSelection(() => props.entries.data, {
    prop: 'matchingIds',
    ids: () => props.matchingIds,
});

/** Whose Work Entry a row is and when, naming its checkbox and buttons. */
function describe(entry: ApprovalEntry): string {
    return `${entry.employee.name}'s Work Entry on ${formatDate(entry.date)}`;
}

function showFiltered(query: Record<string, string>): void {
    clear();
    reloadList(index.url({ query: { ...query, tab: 'work-entries' } }));
}

function approve(): void {
    submit(ApprovalController.store.url());
}
</script>

<template>
    <div class="flex flex-col gap-6">
        <ApprovalFilterBar
            v-if="employees.length > 0"
            :filters="filters"
            :employees="employees"
            :projects="projects"
            @change="showFiltered"
        />

        <SelectionBar
            :count="selected.length"
            :matching="entries.total"
            @select-all="selectAllMatching"
            @clear="clear"
        >
            <template #actions>
                <DeclineSelectedDialog
                    :ids="selected"
                    :action="WorkEntryDeclineController.store()"
                    :noun="{ one: 'Work Entry', many: 'Work Entries' }"
                    description="The Employee sees the reason under each one, and can change or delete it. Declined hours aren't counted."
                    placeholder="Interviews go on Internal, not Acme"
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

        <DataTable :columns="columns" :rows="entries.data">
            <template #head-select>
                <Checkbox
                    v-if="entries.data.length > 0"
                    :model-value="isPageSelected"
                    aria-label="Select this page"
                    @update:model-value="togglePage($event === true)"
                />
            </template>
            <template #cell-select="{ row }">
                <Checkbox
                    :model-value="isSelected(row.id)"
                    :aria-label="`Select ${describe(row)}`"
                    @update:model-value="toggle(row.id, $event === true)"
                />
            </template>
            <template #cell-date="{ row }">
                <span class="whitespace-nowrap">{{
                    formatDate(row.date)
                }}</span>
                <span
                    class="block whitespace-nowrap text-muted-foreground tabular-nums sm:hidden"
                    >{{ formatHours(row.hours) }}</span
                >
            </template>
            <template #cell-employee="{ row }">
                {{ row.employee.name }}
            </template>
            <template #cell-hours="{ row }">
                <span class="whitespace-nowrap tabular-nums">{{
                    formatHours(row.hours)
                }}</span>
            </template>
            <template #cell-answer="{ row }">
                <div class="flex flex-wrap items-center justify-end gap-1">
                    <Form
                        v-bind="ApprovalController.store.form()"
                        :options="{ preserveScroll: true }"
                        v-slot="{ processing: approving }"
                        @success="toggle(row.id, false)"
                    >
                        <input type="hidden" name="ids[]" :value="row.id" />
                        <SubmitButton
                            :processing="approving"
                            variant="outline"
                            size="sm"
                            :aria-label="`Approve ${describe(row)}`"
                            >Approve</SubmitButton
                        >
                    </Form>
                    <DeclineSelectedDialog
                        :ids="[row.id]"
                        :action="WorkEntryDeclineController.store()"
                        :noun="{ one: 'Work Entry', many: 'Work Entries' }"
                        description="The Employee sees the reason under it, and can change or delete it. Declined hours aren't counted."
                        placeholder="Interviews go on Internal, not Acme"
                        size="sm"
                        :accessible-name="`Decline ${describe(row)}`"
                        @declined="toggle(row.id, false)"
                    />
                </div>
            </template>
            <template #empty>
                <EmptyState
                    :icon="CheckCheck"
                    message="Pending Work Entries waiting for your approval appear here. None are waiting."
                />
            </template>
        </DataTable>

        <p
            v-if="entries.total > 0"
            class="flex justify-between gap-4 px-4 text-sm font-medium"
        >
            <span>Total of all {{ entries.total }} matching</span>
            <span class="tabular-nums">{{ formatHours(total) }}</span>
        </p>

        <Pagination :page="entries" preserve-state />
    </div>
</template>

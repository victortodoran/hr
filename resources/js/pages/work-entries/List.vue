<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Clock } from '@lucide/vue';
import { computed } from 'vue';
import ReopeningController from '@/actions/App/Http/Controllers/WorkEntries/ReopeningController';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import DataTable from '@/components/DataTable.vue';
import EmptyState from '@/components/EmptyState.vue';
import PageHeader from '@/components/PageHeader.vue';
import ScopeSwitch from '@/components/ScopeSwitch.vue';
import ListFilterBar from '@/components/ListFilterBar.vue';
import PageContainer from '@/components/PageContainer.vue';
import Pagination from '@/components/Pagination.vue';
import SelectionBar from '@/components/SelectionBar.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import WorkEntryExportMenu from '@/components/work-entries/WorkEntryExportMenu.vue';
import WorkEntrySummary from '@/components/work-entries/WorkEntrySummary.vue';
import { useBulkSelection } from '@/composables/useBulkSelection';
import { reloadList } from '@/composables/useListFilters';
import { formatDate, formatWeekdayAndDay } from '@/lib/dates';
import { formatHours } from '@/lib/work-entries';
import { index as week } from '@/routes/work-entries';
import { index } from '@/routes/work-entry-list';
import type {
    Choice,
    EmployeeWithCount,
    ListedWorkEntry,
    Paginated,
    StateChoice,
    TableColumn,
    WorkEntryListFilters,
    WorkEntryState,
} from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Work Entries', href: index() }],
    },
});

/**
 * Work Entries of the Employees the viewer may see, a Period and an Employee
 * at a time, or every Employee at once, with reopening the selected ones.
 * Approving happens on Approvals.
 */
const props = defineProps<{
    filters: WorkEntryListFilters;
    today: string;
    canReopen: boolean;
    entries: Paginated<ListedWorkEntry>;
    /** Total hours of every Work Entry matching the filters. */
    total: number;
    /** Total hours of the matching Declined Work Entries, which count nowhere. */
    declinedTotal: number;
    /** Every matching Work Entry's ID; only sent when asked for. */
    matchingIds?: number[];
    /** Who has Work Entries in the Period, by name, with how many. */
    employees: EmployeeWithCount[];
    /** The Projects the Employee shown, or anyone, has Work Entries on in the Period. */
    projects: Choice[];
}>();

const states: StateChoice<WorkEntryState>[] = [
    { value: 'pending', label: 'Pending' },
    { value: 'approved', label: 'Approved' },
    { value: 'declined', label: 'Declined' },
];

/**
 * The list's columns: a checkbox when the viewer can reopen, then Day,
 * Employee, Description, Project, Status and Hours. On a phone a row keeps
 * the checkbox, Day, Employee and Hours, with the Status, and why it was
 * Declined, under the name.
 */
const columns = computed<TableColumn[]>(() => [
    ...(props.canReopen ? [{ key: 'select', label: 'Select' }] : []),
    { key: 'date', label: 'Day' },
    { key: 'employee', label: 'Employee' },
    { key: 'description', label: 'Description', minor: true },
    { key: 'project', label: 'Project', minor: true },
    { key: 'state', label: 'Status', minor: true },
    { key: 'hours', label: 'Hours', align: 'end' },
]);

function showFiltered(query: Record<string, string>): void {
    clear();
    reloadList(index.url({ query }));
}

const selectableOnPage = computed(() =>
    props.entries.data.filter((entry) => entry.can_reopen),
);

const {
    selected,
    isPageSelected,
    isSelected,
    toggle,
    togglePage,
    selectAllMatching,
    clear,
} = useBulkSelection(() => selectableOnPage.value, {
    prop: 'matchingIds',
    ids: () => props.matchingIds,
});

/** Selecting every match is only useful when they are all Approved, the state the viewer can act on. */
const canSelectAllMatching = computed(
    () =>
        props.entries.total > 0 &&
        props.filters.state === 'approved' &&
        props.canReopen,
);

/**
 * The week on the Work page holding the Work Entry, for its Employee.
 */
function weekOf(entry: ListedWorkEntry): string {
    return week.url({
        query: { employee: entry.employee.id, week: entry.date },
    });
}
</script>

<template>
    <Head title="Work Entries" />

    <PageContainer width="full">
        <PageHeader
            title="Work Entries"
            :description="
                canReopen
                    ? 'Hours across Employees, to check and reopen'
                    : 'Hours across Employees'
            "
        >
            <template #actions>
                <WorkEntryExportMenu
                    :filters="filters"
                    :disabled="entries.total === 0"
                />
            </template>
        </PageHeader>
        <ScopeSwitch area="work" />

        <ListFilterBar
            :filters="filters"
            :today="today"
            :employees="employees"
            :projects="projects"
            :states="states"
            @change="showFiltered"
        />

        <WorkEntrySummary
            v-if="entries.total > 0"
            :total="total"
            :declined-hours="declinedTotal"
            :caption="`in ${entries.total} matching ${entries.total === 1 ? 'Work Entry' : 'Work Entries'}`"
        />

        <div class="flex flex-col gap-3">
            <SelectionBar
                v-if="canReopen"
                :count="selected.length"
                :matching="canSelectAllMatching ? entries.total : undefined"
                @select-all="selectAllMatching"
                @clear="clear"
            >
                <template #actions>
                    <ConfirmDialog
                        :form="ReopeningController.store.form()"
                        :title="`Reopen ${selected.length === 1 ? '1 Work Entry' : `${selected.length} Work Entries`}?`"
                        :description="
                            selected.length === 1
                                ? 'It goes back to Pending so it can be corrected.'
                                : 'They go back to Pending so they can be corrected.'
                        "
                        confirm-label="Reopen"
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
                                >Reopen</Button
                            >
                        </template>
                    </ConfirmDialog>
                </template>
            </SelectionBar>

            <DataTable :columns="columns" :rows="entries.data">
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
                        :aria-label="`Select ${row.employee.name}'s Work Entry on ${formatDate(row.date)}`"
                        @update:model-value="toggle(row.id, $event === true)"
                    />
                </template>
                <template #cell-date="{ row }">
                    <span class="whitespace-nowrap" data-test="work-entry">{{
                        formatWeekdayAndDay(row.date)
                    }}</span>
                </template>
                <template #cell-employee="{ row }">
                    <Link
                        :href="weekOf(row)"
                        class="font-medium underline-offset-4 hover:underline"
                        >{{ row.employee.name }}</Link
                    >
                    <div class="mt-1 sm:hidden">
                        <StatusBadge :state="row.state" />
                        <span
                            v-if="row.declined"
                            class="mt-1 block text-xs text-status-declined-foreground"
                            >{{ row.declined.reason }}</span
                        >
                    </div>
                </template>
                <template #cell-description="{ row }">
                    <span
                        :class="
                            row.declined && 'text-muted-foreground line-through'
                        "
                        >{{ row.description }}</span
                    >
                    <span
                        v-if="row.approved"
                        class="block text-xs text-muted-foreground"
                        >Approved by {{ row.approved.by }} on
                        {{ formatDate(row.approved.on) }}</span
                    >
                    <span
                        v-if="row.declined"
                        class="block text-xs text-status-declined-foreground"
                        data-test="decline-reason"
                        >Declined by {{ row.declined.by }}:
                        {{ row.declined.reason }}</span
                    >
                </template>
                <template #cell-state="{ row }">
                    <StatusBadge :state="row.state" />
                </template>
                <template #cell-hours="{ row }">
                    <span
                        :class="[
                            'whitespace-nowrap tabular-nums',
                            row.declined &&
                                'text-muted-foreground line-through',
                        ]"
                        >{{ formatHours(row.hours) }}</span
                    >
                </template>
                <template #empty>
                    <EmptyState
                        :icon="Clock"
                        :message="
                            employees.length > 0
                                ? 'No Work Entries match these filters.'
                                : 'No Work Entries in this period.'
                        "
                    />
                </template>
            </DataTable>

            <Pagination :page="entries" preserve-state />
        </div>
    </PageContainer>
</template>

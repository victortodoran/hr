<script setup lang="ts">
import { Check, CheckCheck, X } from '@lucide/vue';
import { computed } from 'vue';
import ApprovalController from '@/actions/App/Http/Controllers/WorkEntries/ApprovalController';
import WorkEntryDeclineController from '@/actions/App/Http/Controllers/WorkEntries/WorkEntryDeclineController';
import ApprovalFilterBar from '@/components/approvals/ApprovalFilterBar.vue';
import DataTable from '@/components/DataTable.vue';
import DecideDialog from '@/components/DecideDialog.vue';
import EmptyState from '@/components/EmptyState.vue';
import FactList from '@/components/FactList.vue';
import Pagination from '@/components/Pagination.vue';
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
import { reloadList } from '@/composables/useListFilters';
import { useApproveOne } from '@/composables/useApproveOne';
import { useRecordInUrl } from '@/composables/useRecordInUrl';
import { formatDate, formatWeekdayAndDay, mondayOf } from '@/lib/dates';
import { formatHours } from '@/lib/work-entries';
import { index } from '@/routes/approvals';
import type {
    ApprovalEntry,
    ApprovalFilters,
    ApprovalWeek,
    ChoiceToApprove,
    Paginated,
    TableColumn,
} from '@/types';

const props = defineProps<{
    filters: ApprovalFilters;
    entries: Paginated<ApprovalEntry>;
    total: number;
    weeks: ApprovalWeek[];
    waiting: number;
    /** Only sent when asked for. */
    matchingIds?: number[];
    employees: ChoiceToApprove[];
    projects: ChoiceToApprove[];
}>();

const columns: TableColumn[] = [
    { key: 'date', label: 'Date', phone: 'secondary' },
    { key: 'description', label: 'Description', phone: 'primary' },
    { key: 'project', label: 'Project', minor: true, phone: 'secondary' },
    { key: 'hours', label: 'Hours', align: 'end', phone: 'end' },
    { key: 'actions', label: '', align: 'end', minor: true },
];

const selection = useBulkSelection(() => props.entries.data, {
    prop: 'matchingIds',
    ids: () => props.matchingIds,
});
const { selected, clear, processing, submit } = selection;

const { record, recordOpen, openRecord, closeRecord } = useRecordInUrl(
    () => props.entries.data,
);

const { approving, approveOne: approve } = useApproveOne();

const otherPending = computed(() => {
    const open = record.value;

    return open === null
        ? []
        : props.entries.data.filter(
              (entry) =>
                  entry.id !== open.id &&
                  mondayOf(entry.date) === mondayOf(open.date),
          );
});

const employeeName = computed(
    () =>
        props.employees.find(
            (employee) => employee.id === props.filters.employee,
        )?.name ?? '',
);

const projectName = computed(
    () =>
        props.projects.find((project) => project.id === props.filters.project)
            ?.name ?? '',
);

const weekOf = (monday: string): ApprovalWeek | undefined =>
    props.weeks.find((week) => week.starts_on === monday);

const selectedOnPage = computed(() =>
    props.entries.data.filter((entry) => selection.isSelected(entry.id)),
);

/** A selection spread over pages has no total, as only this page's hours are here. */
const selectedHours = computed((): string | undefined => {
    if (selected.value.length === props.entries.total) {
        return formatHours(props.total);
    }

    if (selected.value.length !== selectedOnPage.value.length) {
        return undefined;
    }

    return formatHours(
        selectedOnPage.value.reduce((sum, entry) => sum + entry.hours, 0),
    );
});

function describe(entry: ApprovalEntry): string {
    return `${entry.employee.name}'s Work Entry on ${formatDate(entry.date)}`;
}

function summaryOf(entries: ApprovalEntry[]): string {
    const hours = entries.reduce((sum, entry) => sum + entry.hours, 0);
    const days =
        entries.length === 1
            ? formatDate(entries[0].date)
            : `${entries.length} days`;

    return `${employeeName.value} · ${days} · ${formatHours(hours)} on ${projectName.value}`;
}

function showFiltered(query: Record<string, string>): void {
    clear();
    reloadList(index.url({ query: { ...query, tab: 'work-entries' } }));
}

function approveSelected(): void {
    submit(ApprovalController.store.url());
}

function approveOne(entry: ApprovalEntry): void {
    approve(entry.id, ApprovalController.store.url(), { ids: [entry.id] }, () =>
        declinedOne(entry),
    );
}

function declinedOne(entry: ApprovalEntry): void {
    selection.toggle(entry.id, false);
    closeRecord();
}

const noun = { one: 'Work Entry', many: 'Work Entries' };
const declineHelp =
    "The Employee sees this reason on each one, and can edit or delete it. Declined hours aren't counted.";
const declinePlaceholder = 'Interviews go on Internal, not Acme';
</script>

<template>
    <div class="flex flex-col gap-5">
        <ApprovalFilterBar
            v-if="employees.length > 0"
            :filters="filters"
            :employees="employees"
            :projects="projects"
            @change="showFiltered"
        >
            <template #summary>
                <span class="tabular-nums"
                    ><span class="font-semibold text-foreground">{{
                        entries.total
                    }}</span>
                    of {{ waiting }} shown ·
                    <span class="font-semibold text-foreground">{{
                        formatHours(total)
                    }}</span></span
                >
            </template>
        </ApprovalFilterBar>

        <EmptyState
            v-if="employees.length === 0"
            :icon="CheckCheck"
            message="Pending Work Entries waiting for your approval appear here. None are waiting."
        />

        <DataTable
            v-else
            :columns="columns"
            :rows="entries.data"
            :group-by="(entry) => mondayOf(entry.date)"
            :selection="selection"
            :row-label="describe"
            :group-label="(monday) => `Week ${weekOf(monday)?.number}`"
            :on-row-click="(entry) => openRecord(entry.id)"
        >
            <template #lead>
                <div
                    class="flex items-center justify-between gap-3 px-4 py-2.5"
                >
                    <span
                        ><span class="font-semibold">{{ employeeName }}</span>
                        <span class="text-muted-foreground">
                            · {{ projectName }} · {{ entries.total }} Pending
                            {{
                                entries.total === 1 ? noun.one : noun.many
                            }}</span
                        ></span
                    >
                    <span
                        class="font-semibold whitespace-nowrap tabular-nums"
                        >{{ formatHours(total) }}</span
                    >
                </div>
            </template>
            <template #group="{ group }">
                <div class="flex items-center justify-between gap-3">
                    <span
                        ><span class="font-semibold"
                            >Week {{ weekOf(group)?.number }}</span
                        >
                        <span class="text-muted-foreground">
                            ·
                            {{ formatDate(group) }} –
                            {{
                                formatDate(weekOf(group)?.ends_on ?? group)
                            }}</span
                        ></span
                    >
                    <span class="font-semibold tabular-nums">{{
                        formatHours(weekOf(group)?.hours ?? 0)
                    }}</span>
                </div>
            </template>
            <template #cell-date="{ row }">
                <span class="whitespace-nowrap tabular-nums">{{
                    formatWeekdayAndDay(row.date)
                }}</span>
            </template>
            <template #cell-description="{ row }">
                <span class="line-clamp-2">{{ row.description }}</span>
            </template>
            <template #cell-hours="{ row }">
                <span class="whitespace-nowrap tabular-nums">{{
                    formatHours(row.hours)
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
                            :action="WorkEntryDeclineController.store()"
                            :noun="noun"
                            :summary="summaryOf([row])"
                            :description="declineHelp"
                            :placeholder="declinePlaceholder"
                            @declined="declinedOne(row)"
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

        <Pagination :page="entries" preserve-state />

        <SelectionBar
            :count="selected.length"
            :matching="
                selection.isPageSelected.value &&
                entries.total > entries.data.length
                    ? entries.total
                    : undefined
            "
            :total="selectedHours"
            @select-all="selection.selectAllMatching"
            @clear="clear"
        >
            <template #actions>
                <DecideDialog
                    :ids="selected"
                    :action="WorkEntryDeclineController.store()"
                    :noun="noun"
                    :summary="
                        selected.length === selectedOnPage.length
                            ? summaryOf(selectedOnPage)
                            : `${employeeName} · ${projectName}`
                    "
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
            :title="`${formatHours(record.hours)} on ${formatDate(record.date)}`"
            :description="`${record.employee.name}'s Work Entry · Pending`"
        >
            <FactList
                :facts="[
                    { label: 'Employee', value: record.employee.name },
                    { label: 'Project', value: record.project },
                    { label: 'Date', value: formatDate(record.date) },
                    { label: 'Hours', value: formatHours(record.hours) },
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
                    Other Pending Work Entries that week
                </h3>
                <ul class="flex flex-col gap-1">
                    <li v-for="other in otherPending" :key="other.id">
                        {{ formatWeekdayAndDay(other.date) }} ·
                        {{ formatHours(other.hours) }} ·
                        {{ other.description }}
                    </li>
                </ul>
            </section>
            <template #footer>
                <DecideDialog
                    :ids="[record.id]"
                    :action="WorkEntryDeclineController.store()"
                    :noun="noun"
                    :summary="summaryOf([record])"
                    :description="declineHelp"
                    :placeholder="declinePlaceholder"
                    @declined="declinedOne(record)"
                />
                <SubmitButton
                    type="button"
                    :processing="approving === record.id"
                    @click="approveOne(record)"
                    ><Check />Approve Work Entry</SubmitButton
                >
            </template>
        </RecordSheet>
    </div>
</template>

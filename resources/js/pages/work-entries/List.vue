<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Clock } from '@lucide/vue';
import { computed, ref } from 'vue';
import ReopeningController from '@/actions/App/Http/Controllers/ReopeningController';
import DataTable from '@/components/DataTable.vue';
import EmptyState from '@/components/EmptyState.vue';
import Heading from '@/components/Heading.vue';
import PageContainer from '@/components/PageContainer.vue';
import Pagination from '@/components/Pagination.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import LockDialog from '@/components/work-entries/LockDialog.vue';
import WorkEntryListFilterBar from '@/components/work-entries/WorkEntryListFilterBar.vue';
import { formatDate, formatMonth } from '@/lib/dates';
import { index as week } from '@/routes/work-entries';
import { index } from '@/routes/work-entry-list';
import type {
    Choice,
    EmployeeWithWorkEntries,
    ListedWorkEntry,
    Paginated,
    TableColumn,
    WorkEntryListFilters,
} from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Work Entries', href: index() }],
    },
});

/**
 * Work Entries of the Employees the viewer may see, a month and an Employee
 * at a time, or every Employee at once, with reopening and locking the
 * selected ones. Approving happens on Approvals.
 */
const props = defineProps<{
    filters: WorkEntryListFilters;
    canReopen: boolean;
    canLock: boolean;
    entries: Paginated<ListedWorkEntry>;
    /** Total hours of every Work Entry matching the filters. */
    total: number;
    /** Every matching Work Entry's ID; only sent when asked for. */
    matchingIds?: number[];
    /** Who has Work Entries that month, by name, with how many. */
    employees: EmployeeWithWorkEntries[];
    /** The Projects the Employee shown, or anyone, has Work Entries on that month. */
    projects: Choice[];
}>();

const columns: TableColumn[] = [
    ...(props.canReopen || props.canLock
        ? [{ key: 'select', label: 'Select' }]
        : []),
    { key: 'date', label: 'Date' },
    { key: 'employee', label: 'Employee' },
    { key: 'project', label: 'Project', minor: true },
    { key: 'description', label: 'Description', minor: true },
    { key: 'hours', label: 'Hours', align: 'end' },
];

/** The IDs of the selected Work Entries, kept across pages. */
const selected = ref<number[]>([]);

function reloadList(query: Record<string, string>): void {
    selected.value = [];
    router.get(
        index.url({ query }),
        {},
        { preserveState: true, replace: true },
    );
}

const selectableOnPage = computed(() =>
    props.entries.data.filter((entry) => entry.can_reopen || entry.can_lock),
);

const isPageSelected = computed(
    () =>
        selectableOnPage.value.length > 0 &&
        selectableOnPage.value.every((entry) =>
            selected.value.includes(entry.id),
        ),
);

function toggle(id: number, checked: boolean): void {
    selected.value = checked
        ? [...selected.value, id]
        : selected.value.filter((selectedId) => selectedId !== id);
}

function togglePage(checked: boolean): void {
    const onPage = selectableOnPage.value.map((entry) => entry.id);
    const others = selected.value.filter((id) => !onPage.includes(id));
    selected.value = checked ? [...others, ...onPage] : others;
}

/** Selecting every match is only useful when they are all Approved, the state the viewer can act on. */
const canSelectAllMatching = computed(
    () =>
        props.entries.total > 0 &&
        props.filters.state === 'approved' &&
        (props.canReopen || props.canLock),
);

function selectAllMatching(): void {
    router.reload({
        only: ['matchingIds'],
        onSuccess: () => {
            selected.value = [...(props.matchingIds ?? [])];
        },
    });
}

function reopen(): void {
    router.post(
        ReopeningController.store.url(),
        { ids: selected.value },
        {
            preserveScroll: true,
            onSuccess: () => {
                selected.value = [];
            },
        },
    );
}

/**
 * The week on My Work Entries holding the Work Entry, for its Employee.
 */
function weekOf(entry: ListedWorkEntry): string {
    return week.url({
        query: { employee: entry.employee.id, week: entry.date },
    });
}

/**
 * Hours as people read them, such as "7.5 h".
 */
function formatHours(hours: number): string {
    return `${hours.toLocaleString(undefined, { maximumFractionDigits: 2 })} h`;
}

/**
 * Who approved and locked a Work Entry, such as "Approved by Ben on
 * 10 Nov 2026 · Locked by Fay on 11 Nov 2026".
 */
function signOffs(entry: ListedWorkEntry): string {
    return [
        entry.approved &&
            `Approved by ${entry.approved.by} on ${formatDate(entry.approved.on)}`,
        entry.locked &&
            `Locked by ${entry.locked.by} on ${formatDate(entry.locked.on)}`,
    ]
        .filter(Boolean)
        .join(' · ');
}
</script>

<template>
    <Head title="Work Entries" />

    <PageContainer width="wide">
        <Heading
            title="Work Entries"
            :description="
                canReopen || canLock
                    ? 'Hours across Employees, to check, reopen and lock'
                    : 'Hours across Employees'
            "
        />

        <div class="flex flex-col gap-6">
            <WorkEntryListFilterBar
                :filters="filters"
                :employees="employees"
                :projects="projects"
                @change="reloadList"
            />

            <div
                v-if="canReopen || canLock"
                class="flex flex-wrap items-center justify-between gap-3 text-sm"
            >
                <div class="flex items-center gap-3">
                    <span class="text-muted-foreground"
                        >{{ selected.length }} selected</span
                    >
                    <Button
                        v-if="canSelectAllMatching"
                        variant="link"
                        size="sm"
                        class="px-0"
                        @click="selectAllMatching"
                        >Select all {{ entries.total }} matching</Button
                    >
                    <Button
                        v-if="selected.length > 0"
                        variant="link"
                        size="sm"
                        class="px-0"
                        @click="selected = []"
                        >Clear</Button
                    >
                </div>
                <div class="flex gap-2">
                    <LockDialog
                        v-if="canLock"
                        :ids="selected"
                        :filters="filters"
                        @locked="selected = []"
                    />
                    <Button
                        v-if="canReopen"
                        variant="outline"
                        :disabled="selected.length === 0"
                        @click="reopen"
                        >Reopen</Button
                    >
                </div>
            </div>

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
                        v-if="row.can_reopen || row.can_lock"
                        :model-value="selected.includes(row.id)"
                        :aria-label="`Select ${row.employee.name}'s Work Entry on ${formatDate(row.date)}`"
                        @update:model-value="toggle(row.id, $event === true)"
                    />
                </template>
                <template #cell-date="{ row }">
                    <Link
                        :href="weekOf(row)"
                        class="whitespace-nowrap underline-offset-4 hover:underline"
                        >{{ formatDate(row.date) }}</Link
                    >
                </template>
                <template #cell-employee="{ row }">
                    <Link
                        :href="weekOf(row)"
                        class="underline-offset-4 hover:underline"
                        >{{ row.employee.name }}</Link
                    >
                </template>
                <template #cell-description="{ row }">
                    {{ row.description }}
                    <span
                        v-if="row.approved"
                        class="block text-xs text-muted-foreground"
                        >{{ signOffs(row) }}</span
                    >
                </template>
                <template #cell-hours="{ row }">
                    <span
                        class="inline-flex items-center gap-2 whitespace-nowrap"
                    >
                        <StatusBadge :state="row.state" />
                        <span class="tabular-nums">{{
                            formatHours(row.hours)
                        }}</span>
                    </span>
                </template>
                <template #empty>
                    <EmptyState
                        :icon="Clock"
                        :message="
                            employees.length > 0
                                ? 'No Work Entries match these filters.'
                                : `No Work Entries in ${formatMonth(filters.month)}.`
                        "
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

            <Pagination :page="entries" />
        </div>
    </PageContainer>
</template>

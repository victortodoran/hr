<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { CheckCheck } from '@lucide/vue';
import { computed, ref } from 'vue';
import ApprovalController from '@/actions/App/Http/Controllers/ApprovalController';
import LockDialog from '@/components/approvals/LockDialog.vue';
import DataTable from '@/components/DataTable.vue';
import EmptyState from '@/components/EmptyState.vue';
import Pagination from '@/components/Pagination.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { formatDate } from '@/lib/dates';
import { index } from '@/routes/approvals';
import type {
    ApprovalEntry,
    ApprovalFilters,
    Choice,
    Paginated,
    TableColumn,
    WorkEntryState,
} from '@/types';

/**
 * The Work Entries tab of the Approvals page: the filters, the matching Work
 * Entries and approving, reopening or locking the selected ones.
 */
const props = defineProps<{
    filters: ApprovalFilters;
    canApprove: boolean;
    canReopen: boolean;
    canLock: boolean;
    entries: Paginated<ApprovalEntry>;
    /** Total hours of every Work Entry matching the filters. */
    total: number;
    /** Every matching Work Entry's ID; only sent when asked for. */
    matchingIds?: number[];
    employees: Choice[];
    projects: Choice[];
}>();

const states: { value: WorkEntryState; label: string }[] = [
    { value: 'pending', label: 'Pending' },
    { value: 'approved', label: 'Approved' },
    { value: 'locked', label: 'Locked' },
];

const columns: TableColumn[] = [
    { key: 'select', label: 'Select' },
    { key: 'date', label: 'Date' },
    { key: 'employee', label: 'Employee' },
    { key: 'project', label: 'Project', minor: true },
    { key: 'description', label: 'Description', minor: true },
    { key: 'hours', label: 'Hours', align: 'end' },
];

const employee = ref(props.filters.employee?.toString() ?? 'all');
const project = ref(props.filters.project?.toString() ?? 'all');
const from = ref(props.filters.from ?? '');
const to = ref(props.filters.to ?? '');
const state = ref<WorkEntryState>(props.filters.state);

/** The IDs of the selected Work Entries, kept across pages. */
const selected = ref<number[]>([]);

function reloadList(): void {
    selected.value = [];
    router.get(
        index.url({
            query: {
                ...(employee.value !== 'all'
                    ? { employee: employee.value }
                    : {}),
                ...(project.value !== 'all' ? { project: project.value } : {}),
                ...(from.value ? { from: from.value } : {}),
                ...(to.value ? { to: to.value } : {}),
                state: state.value,
                tab: 'work-entries',
            },
        }),
        {},
        { preserveState: true, replace: true },
    );
}

const selectableOnPage = computed(() =>
    props.entries.data.filter(
        (entry) => entry.can_approve || entry.can_reopen || entry.can_lock,
    ),
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

function selectAllMatching(): void {
    router.reload({
        only: ['matchingIds'],
        onSuccess: () => {
            selected.value = [...(props.matchingIds ?? [])];
        },
    });
}

/** Whether the state shown is one the viewer can act on, so selecting every match is useful. */
const canActOnState = computed(
    () =>
        (props.filters.state === 'pending' && props.canApprove) ||
        (props.filters.state === 'approved' &&
            (props.canReopen || props.canLock)),
);

const afterAction = {
    preserveScroll: true,
    onSuccess: () => {
        selected.value = [];
    },
};

function approve(): void {
    router.post(
        ApprovalController.store.url(),
        { ids: selected.value },
        afterAction,
    );
}

function reopen(): void {
    router.delete(ApprovalController.destroy.url(), {
        data: { ids: selected.value },
        ...afterAction,
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
function signOffs(entry: ApprovalEntry): string {
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
    <div class="flex flex-col gap-6">
        <div class="flex flex-wrap items-end gap-3">
            <Select v-model="employee" @update:model-value="reloadList">
                <SelectTrigger class="w-48" aria-label="Employee">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="all">Any Employee</SelectItem>
                    <SelectItem
                        v-for="choice in employees"
                        :key="choice.id"
                        :value="String(choice.id)"
                        >{{ choice.name }}</SelectItem
                    >
                </SelectContent>
            </Select>
            <Select v-model="project" @update:model-value="reloadList">
                <SelectTrigger class="w-48" aria-label="Project">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="all">Any Project</SelectItem>
                    <SelectItem
                        v-for="choice in projects"
                        :key="choice.id"
                        :value="String(choice.id)"
                        >{{ choice.name }}</SelectItem
                    >
                </SelectContent>
            </Select>
            <Input
                v-model="from"
                type="date"
                class="w-40"
                aria-label="From"
                @change="reloadList"
            />
            <Input
                v-model="to"
                type="date"
                class="w-40"
                aria-label="To"
                @change="reloadList"
            />
            <Select v-model="state" @update:model-value="reloadList">
                <SelectTrigger class="w-36" aria-label="State">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem
                        v-for="choice in states"
                        :key="choice.value"
                        :value="choice.value"
                        >{{ choice.label }}</SelectItem
                    >
                </SelectContent>
            </Select>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-3 text-sm">
            <div class="flex items-center gap-3">
                <span class="text-muted-foreground"
                    >{{ selected.length }} selected</span
                >
                <Button
                    v-if="entries.total > 0 && canActOnState"
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
                <Button
                    v-if="canApprove"
                    :disabled="selected.length === 0"
                    @click="approve"
                    >Approve</Button
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
                    v-if="row.can_approve || row.can_reopen || row.can_lock"
                    :model-value="selected.includes(row.id)"
                    :aria-label="`Select ${row.employee.name}'s Work Entry on ${formatDate(row.date)}`"
                    @update:model-value="toggle(row.id, $event === true)"
                />
            </template>
            <template #cell-date="{ row }">
                <span class="whitespace-nowrap">{{
                    formatDate(row.date)
                }}</span>
            </template>
            <template #cell-employee="{ row }">
                {{ row.employee.name }}
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
                <span class="inline-flex items-center gap-2 whitespace-nowrap">
                    <StatusBadge
                        v-if="row.state !== 'pending'"
                        :state="row.state"
                    />
                    <span class="tabular-nums">{{
                        formatHours(row.hours)
                    }}</span>
                </span>
            </template>
            <template #empty>
                <EmptyState
                    :icon="CheckCheck"
                    message="Work Entries you may approve, reopen or lock appear here. None match these filters."
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
</template>

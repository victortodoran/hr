<script setup lang="ts">
import { formatHours } from '@/lib/work-entries';
import { router } from '@inertiajs/vue3';
import { CheckCheck } from '@lucide/vue';
import { computed, ref } from 'vue';
import ApprovalController from '@/actions/App/Http/Controllers/ApprovalController';
import ApprovalFilterBar from '@/components/approvals/ApprovalFilterBar.vue';
import DataTable from '@/components/DataTable.vue';
import EmptyState from '@/components/EmptyState.vue';
import Pagination from '@/components/Pagination.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { formatDate } from '@/lib/dates';
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
 * waiting for the viewer's approval, one Employee at a time, and approving
 * the selected ones. With nothing waiting there is no Employee to choose.
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
        index.url({ query: { ...query, tab: 'work-entries' } }),
        {},
        { preserveState: true, replace: true },
    );
}

const isPageSelected = computed(
    () =>
        props.entries.data.length > 0 &&
        props.entries.data.every((entry) => selected.value.includes(entry.id)),
);

function toggle(id: number, checked: boolean): void {
    selected.value = checked
        ? [...selected.value, id]
        : selected.value.filter((selectedId) => selectedId !== id);
}

function togglePage(checked: boolean): void {
    const onPage = props.entries.data.map((entry) => entry.id);
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

function approve(): void {
    router.post(
        ApprovalController.store.url(),
        { ids: selected.value },
        {
            preserveScroll: true,
            onSuccess: () => {
                selected.value = [];
            },
        },
    );
}
</script>

<template>
    <div class="flex flex-col gap-6">
        <ApprovalFilterBar
            v-if="employees.length > 0"
            :filters="filters"
            :employees="employees"
            :projects="projects"
            @change="reloadList"
        />

        <div class="flex flex-wrap items-center justify-between gap-3 text-sm">
            <div class="flex items-center gap-3">
                <span class="text-muted-foreground"
                    >{{ selected.length }} selected</span
                >
                <Button
                    v-if="entries.total > 0"
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
            <Button :disabled="selected.length === 0" @click="approve"
                >Approve</Button
            >
        </div>

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
            <template #cell-hours="{ row }">
                <span class="whitespace-nowrap tabular-nums">{{
                    formatHours(row.hours)
                }}</span>
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

        <Pagination :page="entries" />
    </div>
</template>

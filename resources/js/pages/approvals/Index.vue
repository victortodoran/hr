<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import ApprovalController from '@/actions/App/Http/Controllers/ApprovalController';
import Heading from '@/components/Heading.vue';
import Pagination from '@/components/Pagination.vue';
import { Badge } from '@/components/ui/badge';
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
    WorkEntryState,
} from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Approvals', href: index() }],
    },
});

const props = defineProps<{
    filters: ApprovalFilters;
    canApprove: boolean;
    canReopen: boolean;
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
            },
        }),
        {},
        { preserveState: true, replace: true },
    );
}

const selectableOnPage = computed(() =>
    props.entries.data.filter((entry) => entry.can_approve || entry.can_reopen),
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
        (props.filters.state === 'approved' && props.canReopen),
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
    <Head title="Approvals" />

    <div class="flex max-w-5xl flex-col gap-6 p-4">
        <Heading
            title="Approvals"
            description="Work Entries of the Employees you approve or reopen for"
        />

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

        <div class="overflow-x-auto rounded-lg border">
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-left text-muted-foreground">
                    <tr>
                        <th class="w-10 px-4 py-2">
                            <Checkbox
                                v-if="selectableOnPage.length > 0"
                                :model-value="isPageSelected"
                                aria-label="Select this page"
                                @update:model-value="
                                    togglePage($event === true)
                                "
                            />
                        </th>
                        <th class="px-4 py-2 font-medium">Date</th>
                        <th class="px-4 py-2 font-medium">Employee</th>
                        <th class="px-4 py-2 font-medium">Project</th>
                        <th class="px-4 py-2 font-medium">Description</th>
                        <th class="px-4 py-2 text-right font-medium">Hours</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <tr v-for="entry in entries.data" :key="entry.id">
                        <td class="px-4 py-2">
                            <Checkbox
                                v-if="entry.can_approve || entry.can_reopen"
                                :model-value="selected.includes(entry.id)"
                                :aria-label="`Select ${entry.employee.name}'s Work Entry on ${formatDate(entry.date)}`"
                                @update:model-value="
                                    toggle(entry.id, $event === true)
                                "
                            />
                        </td>
                        <td class="px-4 py-2 whitespace-nowrap">
                            {{ formatDate(entry.date) }}
                        </td>
                        <td class="px-4 py-2">{{ entry.employee.name }}</td>
                        <td class="px-4 py-2">{{ entry.project }}</td>
                        <td class="px-4 py-2">
                            {{ entry.description }}
                            <span
                                v-if="entry.approved"
                                class="block text-xs text-muted-foreground"
                                >{{ signOffs(entry) }}</span
                            >
                        </td>
                        <td class="px-4 py-2 text-right whitespace-nowrap">
                            <Badge
                                v-if="entry.state !== 'pending'"
                                variant="outline"
                                class="mr-2"
                                >{{
                                    entry.state === 'approved'
                                        ? 'Approved'
                                        : 'Locked'
                                }}</Badge
                            >
                            <span class="tabular-nums">{{
                                formatHours(entry.hours)
                            }}</span>
                        </td>
                    </tr>
                    <tr v-if="entries.data.length === 0">
                        <td
                            colspan="6"
                            class="px-4 py-6 text-center text-muted-foreground"
                        >
                            No Work Entries match.
                        </td>
                    </tr>
                </tbody>
                <tfoot v-if="entries.total > 0" class="border-t font-medium">
                    <tr>
                        <td colspan="5" class="px-4 py-2">
                            Total of all {{ entries.total }} matching
                        </td>
                        <td class="px-4 py-2 text-right tabular-nums">
                            {{ formatHours(total) }}
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <Pagination :page="entries" />
    </div>
</template>

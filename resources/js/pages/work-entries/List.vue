<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Clock } from '@lucide/vue';
import { computed, ref } from 'vue';
import ReopeningController from '@/actions/App/Http/Controllers/ReopeningController';
import EmptyState from '@/components/EmptyState.vue';
import Heading from '@/components/Heading.vue';
import PageContainer from '@/components/PageContainer.vue';
import Pagination from '@/components/Pagination.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import LockDialog from '@/components/work-entries/LockDialog.vue';
import WorkEntryListFilterBar from '@/components/work-entries/WorkEntryListFilterBar.vue';
import WorkEntrySummary from '@/components/work-entries/WorkEntrySummary.vue';
import { formatDate, formatWeekdayAndDay } from '@/lib/dates';
import { formatHours } from '@/lib/work-entries';
import { index as week } from '@/routes/work-entries';
import { index } from '@/routes/work-entry-list';
import type {
    Choice,
    EmployeeWithWorkEntries,
    ListedWorkEntry,
    Paginated,
    WorkEntryListFilters,
} from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Work Entries', href: index() }],
    },
});

/**
 * Work Entries of the Employees the viewer may see, a Period and an Employee
 * at a time, or every Employee at once, with reopening and locking the
 * selected ones. Approving happens on Approvals.
 */
const props = defineProps<{
    filters: WorkEntryListFilters;
    today: string;
    canReopen: boolean;
    canLock: boolean;
    entries: Paginated<ListedWorkEntry>;
    /** Total hours of every Work Entry matching the filters. */
    total: number;
    /** Total hours of the matching Declined Work Entries, which count nowhere. */
    declinedTotal: number;
    /** Every matching Work Entry's ID; only sent when asked for. */
    matchingIds?: number[];
    /** Who has Work Entries in the Period, by name, with how many. */
    employees: EmployeeWithWorkEntries[];
    /** The Projects the Employee shown, or anyone, has Work Entries on in the Period. */
    projects: Choice[];
}>();

const canSelect = computed(() => props.canReopen || props.canLock);

/**
 * The list's grid, as on My Work Entries: on a phone each Work Entry stacks
 * into lines; from `sm` up, a checkbox when the viewer can select, then Day,
 * Employee, Description, Project, Status and Hours. Both strings are written
 * out in full so Tailwind finds them; keep their shared tracks alike.
 */
const columns = computed(() =>
    canSelect.value
        ? 'grid-cols-[minmax(0,1fr)_auto] gap-x-3 sm:grid-cols-[1rem_4.5rem_minmax(0,10rem)_minmax(0,1fr)_minmax(0,12rem)_5.5rem_4.5rem]'
        : 'grid-cols-[minmax(0,1fr)_auto] gap-x-3 sm:grid-cols-[4.5rem_minmax(0,10rem)_minmax(0,1fr)_minmax(0,12rem)_5.5rem_4.5rem]',
);

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

/**
 * Whether the header shows on a phone, where it only holds "Select this page"
 * so it is left out when nothing on the page can be selected.
 */
const headerOnPhone = computed(
    () => canSelect.value && selectableOnPage.value.length > 0,
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
        canSelect.value,
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
                canSelect
                    ? 'Hours across Employees, to check, reopen and lock'
                    : 'Hours across Employees'
            "
        />

        <WorkEntryListFilterBar
            :filters="filters"
            :today="today"
            :employees="employees"
            :projects="projects"
            @change="reloadList"
        />

        <WorkEntrySummary
            v-if="entries.total > 0"
            :total="total"
            :declined-hours="declinedTotal"
            :caption="`in ${entries.total} matching ${entries.total === 1 ? 'Work Entry' : 'Work Entries'}`"
        />

        <div class="flex flex-col gap-3">
            <div
                v-if="canSelect"
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

            <div class="overflow-hidden rounded-lg border text-sm">
                <div
                    :class="[
                        columns,
                        headerOnPhone ? 'grid' : 'hidden sm:grid',
                    ]"
                    class="items-center bg-muted/50 px-4 py-2 font-medium text-muted-foreground"
                >
                    <span
                        v-if="canSelect"
                        class="col-span-2 flex items-center sm:col-span-1"
                    >
                        <Label
                            v-if="selectableOnPage.length > 0"
                            class="inline-flex items-center gap-2 font-normal"
                        >
                            <Checkbox
                                :model-value="isPageSelected"
                                aria-label="Select this page"
                                @update:model-value="
                                    togglePage($event === true)
                                "
                            />
                            <span class="sm:hidden">Select this page</span>
                        </Label>
                    </span>
                    <span class="hidden sm:block">Day</span>
                    <span class="hidden sm:block">Employee</span>
                    <span class="hidden sm:block">Description</span>
                    <span class="hidden sm:block">Project</span>
                    <span class="hidden sm:block">Status</span>
                    <span class="hidden text-right sm:block">Hours</span>
                </div>

                <div
                    v-for="(entry, index) in entries.data"
                    :key="entry.id"
                    :class="[
                        columns,
                        index === 0 && !headerOnPhone && 'max-sm:border-t-0',
                    ]"
                    class="grid min-h-11 items-center gap-y-0.5 border-t px-4 py-1.5 hover:bg-muted/40"
                    data-test="work-entry"
                >
                    <span
                        class="col-span-2 flex min-w-0 items-center gap-2 font-medium sm:contents"
                    >
                        <span v-if="canSelect" class="flex size-4">
                            <Checkbox
                                v-if="entry.can_reopen || entry.can_lock"
                                :model-value="selected.includes(entry.id)"
                                :aria-label="`Select ${entry.employee.name}'s Work Entry on ${formatDate(entry.date)}`"
                                @update:model-value="
                                    toggle(entry.id, $event === true)
                                "
                            />
                        </span>
                        <Link
                            :href="weekOf(entry)"
                            class="whitespace-nowrap underline-offset-4 hover:underline"
                            >{{ formatWeekdayAndDay(entry.date) }}</Link
                        >
                        <Link
                            :href="weekOf(entry)"
                            class="truncate font-normal underline-offset-4 hover:underline"
                            >{{ entry.employee.name }}</Link
                        >
                    </span>
                    <span
                        :class="[
                            'truncate',
                            entry.declined &&
                                'text-muted-foreground line-through',
                        ]"
                        :title="entry.description"
                        >{{ entry.description }}</span
                    >
                    <span
                        class="order-2 col-span-2 flex min-w-0 items-center gap-2 sm:contents"
                    >
                        <span class="truncate text-muted-foreground">{{
                            entry.project
                        }}</span>
                        <span><StatusBadge :state="entry.state" /></span>
                    </span>
                    <span
                        :class="[
                            'order-1 text-right font-medium tabular-nums sm:order-none',
                            entry.declined &&
                                'text-muted-foreground line-through',
                        ]"
                        >{{ formatHours(entry.hours) }}</span
                    >
                    <span
                        v-if="entry.approved"
                        class="order-3 col-span-2 text-xs text-muted-foreground sm:order-none sm:col-span-4 sm:-col-end-1"
                        >{{ signOffs(entry) }}</span
                    >
                    <span
                        v-if="entry.declined"
                        class="order-3 col-span-2 text-xs text-status-declined-foreground sm:order-none sm:col-span-4 sm:-col-end-1"
                        data-test="decline-reason"
                        >Declined by {{ entry.declined.by }}:
                        {{ entry.declined.reason }}</span
                    >
                </div>

                <EmptyState
                    v-if="entries.data.length === 0"
                    class="border-t max-sm:border-t-0"
                    :icon="Clock"
                    :message="
                        employees.length > 0
                            ? 'No Work Entries match these filters.'
                            : 'No Work Entries in this period.'
                    "
                />
            </div>

            <Pagination :page="entries" />
        </div>
    </PageContainer>
</template>

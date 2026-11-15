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
import { formatDate, formatMonth, formatWeekdayAndDay } from '@/lib/dates';
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
    /** Total hours of the matching Declined Work Entries, which count nowhere. */
    declinedTotal: number;
    /** Every matching Work Entry's ID; only sent when asked for. */
    matchingIds?: number[];
    /** Who has Work Entries that month, by name, with how many. */
    employees: EmployeeWithWorkEntries[];
    /** The Projects the Employee shown, or anyone, has Work Entries on that month. */
    projects: Choice[];
}>();

const canSelect = computed(() => props.canReopen || props.canLock);

/**
 * The list's grid, as on My Work Entries: on a phone each Work Entry stacks
 * into lines; from `sm` up, a checkbox when the viewer can select, then Day,
 * Employee, Description, Project, Status and Hours.
 */
const columns = computed(() =>
    canSelect.value
        ? 'grid-cols-[minmax(0,1fr)_auto] gap-x-3 sm:grid-cols-[1rem_4.5rem_minmax(0,10rem)_minmax(0,1fr)_minmax(0,12rem)_5.5rem_4.5rem]'
        : 'grid-cols-[minmax(0,1fr)_auto] gap-x-3 sm:grid-cols-[4.5rem_minmax(0,10rem)_minmax(0,1fr)_minmax(0,12rem)_5.5rem_4.5rem]',
);

/** Where a note under a Work Entry starts and ends: under Description, through Hours. */
const noteColumns = computed(() =>
    canSelect.value
        ? 'sm:col-span-4 sm:col-start-4'
        : 'sm:col-span-4 sm:col-start-3',
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
            :employees="employees"
            :projects="projects"
            @change="reloadList"
        />

        <div
            class="flex flex-wrap items-baseline gap-x-6 gap-y-1 text-sm"
            data-test="work-entry-summary"
        >
            <p>
                <span class="text-2xl font-semibold tabular-nums">{{
                    formatHours(total)
                }}</span>
                <span class="ml-1.5 text-muted-foreground"
                    >in {{ formatMonth(filters.month) }}</span
                >
            </p>
            <p class="text-muted-foreground">
                {{ entries.total }}
                {{ entries.total === 1 ? 'Work Entry' : 'Work Entries' }}
            </p>
            <p
                v-if="declinedTotal > 0"
                class="text-status-declined-foreground"
                data-test="declined-hours"
            >
                {{ formatHours(declinedTotal) }} declined, not counted
            </p>
        </div>

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
                    :class="[columns, canSelect ? 'grid' : 'hidden sm:grid']"
                    class="items-center bg-muted/50 px-4 py-2 font-medium text-muted-foreground"
                >
                    <Label
                        v-if="canSelect"
                        class="col-span-2 flex items-center gap-2 font-normal sm:col-span-1"
                        :class="selectableOnPage.length === 0 && 'invisible'"
                    >
                        <Checkbox
                            :model-value="isPageSelected"
                            :disabled="selectableOnPage.length === 0"
                            aria-label="Select this page"
                            @update:model-value="togglePage($event === true)"
                        />
                        <span class="sm:hidden">Select this page</span>
                    </Label>
                    <span class="hidden sm:block">Day</span>
                    <span class="hidden sm:block">Employee</span>
                    <span class="hidden sm:block">Description</span>
                    <span class="hidden sm:block">Project</span>
                    <span class="hidden sm:block">Status</span>
                    <span class="hidden text-right sm:block">Hours</span>
                </div>

                <div
                    v-for="entry in entries.data"
                    :key="entry.id"
                    :class="columns"
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
                        :class="noteColumns"
                        class="order-3 col-span-2 text-xs text-muted-foreground sm:order-none"
                        >{{ signOffs(entry) }}</span
                    >
                    <span
                        v-if="entry.declined"
                        :class="noteColumns"
                        class="order-3 col-span-2 text-xs text-status-declined-foreground sm:order-none"
                        data-test="decline-reason"
                        >Declined by {{ entry.declined.by }}:
                        {{ entry.declined.reason }}</span
                    >
                </div>

                <EmptyState
                    v-if="entries.data.length === 0"
                    class="border-t"
                    :icon="Clock"
                    :message="
                        employees.length > 0
                            ? 'No Work Entries match these filters.'
                            : `No Work Entries in ${formatMonth(filters.month)}.`
                    "
                />
            </div>

            <Pagination :page="entries" />
        </div>
    </PageContainer>
</template>

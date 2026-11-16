<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { Clock } from '@lucide/vue';
import { computed } from 'vue';
import ApprovalController from '@/actions/App/Http/Controllers/ApprovalController';
import ReopeningController from '@/actions/App/Http/Controllers/ReopeningController';
import DeclineWorkEntriesDialog from '@/components/approvals/DeclineWorkEntriesDialog.vue';
import EmptyState from '@/components/EmptyState.vue';
import Pagination from '@/components/Pagination.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import LockDialog from '@/components/work-entries/LockDialog.vue';
import { formatDate, formatWeekdayAndDay } from '@/lib/dates';
import { formatHours } from '@/lib/work-entries';
import { index as week } from '@/routes/work-entries';
import type { ListedWorkEntry, Paginated, WorkEntryListFilters } from '@/types';

/**
 * Work Entries listed for review, a page at a time, with approving,
 * declining, reopening and locking the selected ones, each offered only to
 * a viewer allowed to. Only Work Entries the viewer can act on are
 * selectable. The Employee column is left out where the list is one
 * Employee's.
 */
const props = defineProps<{
    entries: Paginated<ListedWorkEntry>;
    /**
     * The filters the list shows, for the lock confirmation's Pending
     * warning, and for offering to select every match.
     */
    filters: WorkEntryListFilters;
    canDecide: boolean;
    canReopen: boolean;
    canLock: boolean;
    showEmployee: boolean;
    /** What the empty list says. */
    emptyMessage: string;
}>();

const emit = defineEmits<{
    /** Asks for every matching Work Entry's ID, to select them all. */
    selectAllMatching: [];
}>();

/** The IDs of the selected Work Entries, kept across pages. */
const selected = defineModel<number[]>('selected', { required: true });

const canSelect = computed(
    () => props.canDecide || props.canReopen || props.canLock,
);

function isSelectable(entry: ListedWorkEntry): boolean {
    return entry.can_decide === true || entry.can_reopen || entry.can_lock;
}

/**
 * The list's grid, as on My Work Entries: on a phone each Work Entry stacks
 * into lines; from `sm` up, a checkbox when the viewer can select, then Day,
 * the Employee where shown, Description, Project, Status and Hours. Each
 * string is written out in full so Tailwind finds it; keep their shared
 * tracks alike.
 */
const columns = computed(() => {
    if (props.showEmployee) {
        return canSelect.value
            ? 'grid-cols-[minmax(0,1fr)_auto] gap-x-3 sm:grid-cols-[1rem_4.5rem_minmax(0,10rem)_minmax(0,1fr)_minmax(0,12rem)_5.5rem_4.5rem]'
            : 'grid-cols-[minmax(0,1fr)_auto] gap-x-3 sm:grid-cols-[4.5rem_minmax(0,10rem)_minmax(0,1fr)_minmax(0,12rem)_5.5rem_4.5rem]';
    }

    return canSelect.value
        ? 'grid-cols-[minmax(0,1fr)_auto] gap-x-3 sm:grid-cols-[1rem_4.5rem_minmax(0,1fr)_minmax(0,12rem)_5.5rem_4.5rem]'
        : 'grid-cols-[minmax(0,1fr)_auto] gap-x-3 sm:grid-cols-[4.5rem_minmax(0,1fr)_minmax(0,12rem)_5.5rem_4.5rem]';
});

const selectableOnPage = computed(() =>
    props.entries.data.filter(isSelectable),
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

/**
 * Selecting every match is only useful when they are all in the state the
 * viewer can act on: Approved to reopen or lock, Pending to approve or
 * decline.
 */
const canSelectAllMatching = computed(
    () =>
        props.entries.total > 0 &&
        ((props.filters.state === 'approved' &&
            (props.canReopen || props.canLock)) ||
            (props.filters.state === 'pending' && props.canDecide)),
);

function clearSelection(): void {
    selected.value = [];
}

function post(url: string): void {
    router.post(
        url,
        { ids: selected.value },
        { preserveScroll: true, onSuccess: clearSelection },
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
                    @click="emit('selectAllMatching')"
                    >Select all {{ entries.total }} matching</Button
                >
                <Button
                    v-if="selected.length > 0"
                    variant="link"
                    size="sm"
                    class="px-0"
                    @click="clearSelection"
                    >Clear</Button
                >
            </div>
            <div class="flex flex-wrap gap-2">
                <template v-if="canDecide">
                    <DeclineWorkEntriesDialog
                        :ids="selected"
                        @declined="clearSelection"
                    />
                    <Button
                        variant="outline"
                        :disabled="selected.length === 0"
                        @click="post(ApprovalController.store.url())"
                        >Approve</Button
                    >
                </template>
                <LockDialog
                    v-if="canLock"
                    :ids="selected"
                    :filters="filters"
                    @locked="clearSelection"
                />
                <Button
                    v-if="canReopen"
                    variant="outline"
                    :disabled="selected.length === 0"
                    @click="post(ReopeningController.store.url())"
                    >Reopen</Button
                >
            </div>
        </div>

        <div class="overflow-hidden rounded-lg border text-sm">
            <div
                :class="[columns, headerOnPhone ? 'grid' : 'hidden sm:grid']"
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
                            @update:model-value="togglePage($event === true)"
                        />
                        <span class="sm:hidden">Select this page</span>
                    </Label>
                </span>
                <span class="hidden sm:block">Day</span>
                <span v-if="showEmployee" class="hidden sm:block"
                    >Employee</span
                >
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
                            v-if="isSelectable(entry)"
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
                        v-if="showEmployee"
                        :href="weekOf(entry)"
                        class="truncate font-normal underline-offset-4 hover:underline"
                        >{{ entry.employee.name }}</Link
                    >
                </span>
                <span
                    :class="[
                        'truncate',
                        entry.declined && 'text-muted-foreground line-through',
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
                        entry.declined && 'text-muted-foreground line-through',
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
                :message="emptyMessage"
            />
        </div>

        <Pagination :page="entries" />
    </div>
</template>

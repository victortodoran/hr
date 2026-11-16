<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import Heading from '@/components/Heading.vue';
import PageContainer from '@/components/PageContainer.vue';
import WorkEntryListFilterBar from '@/components/work-entries/WorkEntryListFilterBar.vue';
import WorkEntryReview from '@/components/work-entries/WorkEntryReview.vue';
import WorkEntrySummary from '@/components/work-entries/WorkEntrySummary.vue';
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

function selectAllMatching(): void {
    router.reload({
        only: ['matchingIds'],
        onSuccess: () => {
            selected.value = [...(props.matchingIds ?? [])];
        },
    });
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

        <WorkEntryReview
            v-model:selected="selected"
            :entries="entries"
            :filters="filters"
            :can-decide="false"
            :can-reopen="canReopen"
            :can-lock="canLock"
            show-employee
            :empty-message="
                employees.length > 0
                    ? 'No Work Entries match these filters.'
                    : 'No Work Entries in this period.'
            "
            @select-all-matching="selectAllMatching"
        />
    </PageContainer>
</template>

<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Clock } from '@lucide/vue';
import { computed } from 'vue';
import DataTable from '@/components/DataTable.vue';
import EmptyState from '@/components/EmptyState.vue';
import EmployeeListTab from '@/components/employees/EmployeeListTab.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { formatWeekdayAndDay } from '@/lib/dates';
import { formatHours } from '@/lib/work-entries';
import { index as week } from '@/routes/work-entries';
import { index as workEntryList } from '@/routes/work-entry-list';
import type {
    Choice,
    ListedWorkEntry,
    Paginated,
    Period,
    TableColumn,
} from '@/types';

/**
 * The Work tab of an Employee's page: their Work Entries in a Period, as
 * the Work Entries page lists them for this Employee, read-only. Reopening
 * and Export stay on that page, which "Open in Work Entries" opens on the
 * same Period.
 */
const props = defineProps<{
    employee: Choice;
    work: {
        today: string;
        period: Period;
        entries: Paginated<ListedWorkEntry>;
        /** Total hours of the Work Entries that aren't Declined. */
        total: number;
        /** Total hours of the Declined ones, which count nowhere. */
        declinedTotal: number;
    };
}>();

/**
 * Day, Project (with the Status under it on a phone), Description, Status
 * and Hours.
 */
const columns: TableColumn[] = [
    { key: 'date', label: 'Day' },
    { key: 'project', label: 'Project' },
    { key: 'description', label: 'Description', minor: true },
    { key: 'state', label: 'Status', minor: true },
    { key: 'hours', label: 'Hours', align: 'end' },
];

const stats = computed(() => [
    {
        label: props.work.entries.total === 1 ? 'Work Entry' : 'Work Entries',
        value: String(props.work.entries.total),
    },
    { label: 'Hours', value: formatHours(props.work.total) },
    ...(props.work.declinedTotal > 0
        ? [
              {
                  label: 'Declined hours',
                  value: formatHours(props.work.declinedTotal),
                  muted: true,
              },
          ]
        : []),
]);

/** The week on the Work page holding the Work Entry. */
function weekOf(entry: ListedWorkEntry): string {
    return week.url({
        query: { employee: props.employee.id, week: entry.date },
    });
}
</script>

<template>
    <EmployeeListTab
        :employee="employee"
        tab="work"
        :period="work.period"
        :today="work.today"
        no-future
        :stats="stats"
        title="Work Entries"
        list-name="Work Entries"
        :list-href="
            workEntryList({
                query: { employee: employee.id, ...work.period.query },
            })
        "
        :page="work.entries"
    >
        <DataTable :columns="columns" :rows="work.entries.data">
            <template #cell-date="{ row }">
                <Link
                    :href="weekOf(row)"
                    class="font-medium whitespace-nowrap underline-offset-4 hover:underline"
                    data-test="work-entry"
                    >{{ formatWeekdayAndDay(row.date) }}</Link
                >
            </template>
            <template #cell-project="{ row }">
                {{ row.project }}
                <div class="mt-1 sm:hidden">
                    <StatusBadge :state="row.state" />
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
                    v-if="row.declined"
                    class="block text-caption text-status-declined-foreground"
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
                        row.declined && 'text-muted-foreground line-through',
                    ]"
                    >{{ formatHours(row.hours) }}</span
                >
            </template>
            <template #empty>
                <EmptyState
                    :icon="Clock"
                    :message="`${employee.name}'s Work Entries in this period appear here. There are none.`"
                />
            </template>
        </DataTable>
    </EmployeeListTab>
</template>

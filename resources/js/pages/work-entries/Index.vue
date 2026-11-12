<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight } from '@lucide/vue';
import { computed } from 'vue';
import Heading from '@/components/Heading.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import DeleteWorkEntry from '@/components/work-entries/DeleteWorkEntry.vue';
import WorkEntryDialog from '@/components/work-entries/WorkEntryDialog.vue';
import { formatDate } from '@/lib/dates';
import { index } from '@/routes/work-entries';
import type {
    Choice,
    WorkEntryAssignment,
    WorkEntryAssignmentTotal,
    WorkEntryDay,
    WorkEntryRecord,
} from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Work Entries', href: index() }],
    },
});

const props = defineProps<{
    employee: Choice;
    /** The Monday the week starts on. */
    week: string;
    today: string;
    days: WorkEntryDay[];
    assignmentTotals: WorkEntryAssignmentTotal[];
    total: number;
    assignments: WorkEntryAssignment[];
    canManage: boolean;
    /** The Employees the viewer may switch to; null when only their own. */
    employees: Choice[] | null;
}>();

/**
 * The date a number of days away, as a YYYY-MM-DD date.
 */
function addDays(date: string, days: number): string {
    const moved = new Date(`${date}T00:00:00`);
    moved.setDate(moved.getDate() + days);

    return moved.toLocaleDateString('en-CA');
}

/**
 * Hours as people read them, such as "7.5 h".
 */
function formatHours(hours: number): string {
    return `${hours.toLocaleString(undefined, { maximumFractionDigits: 2 })} h`;
}

/**
 * A day's name and date, such as "Mon 9 Nov".
 */
function formatDay(date: string): string {
    return new Date(`${date}T00:00:00`).toLocaleDateString(undefined, {
        weekday: 'short',
        day: 'numeric',
        month: 'short',
    });
}

/**
 * The week page for an Employee and the week holding a day.
 */
function weekOf(employeeId: number, day: string) {
    return index({ query: { employee: employeeId, week: day } });
}

/**
 * Who approved and locked a Work Entry, such as "Approved by Ben on
 * 10 Nov 2026 · Locked by Fay on 11 Nov 2026".
 */
function signOffs(entry: WorkEntryRecord): string {
    return [
        entry.approved &&
            `Approved by ${entry.approved.by} on ${formatDate(entry.approved.on)}`,
        entry.locked &&
            `Locked by ${entry.locked.by} on ${formatDate(entry.locked.on)}`,
    ]
        .filter(Boolean)
        .join(' · ');
}

const isCurrentWeek = computed(
    () => props.today >= props.week && props.today <= addDays(props.week, 6),
);

function switchEmployee(employeeId: unknown): void {
    router.get(weekOf(Number(employeeId), props.week).url);
}
</script>

<template>
    <Head title="Work Entries" />

    <div class="flex max-w-4xl flex-col gap-6 p-4">
        <Heading
            title="Work Entries"
            :description="`Hours ${employee.name} worked, one week at a time`"
        />

        <div class="flex flex-wrap items-center justify-between gap-3">
            <Select
                v-if="employees"
                :model-value="String(employee.id)"
                @update:model-value="switchEmployee"
            >
                <SelectTrigger class="w-56" aria-label="Employee">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem
                        v-for="choice in employees"
                        :key="choice.id"
                        :value="String(choice.id)"
                        >{{ choice.name }}</SelectItem
                    >
                </SelectContent>
            </Select>

            <div class="flex items-center gap-2">
                <Button variant="outline" size="icon" as-child>
                    <Link
                        :href="weekOf(employee.id, addDays(week, -7))"
                        aria-label="Previous week"
                    >
                        <ChevronLeft />
                    </Link>
                </Button>
                <span class="text-sm font-medium">
                    {{ formatDate(week) }} – {{ formatDate(addDays(week, 6)) }}
                </span>
                <Button variant="outline" size="icon" as-child>
                    <Link
                        :href="weekOf(employee.id, addDays(week, 7))"
                        aria-label="Next week"
                    >
                        <ChevronRight />
                    </Link>
                </Button>
                <Button
                    v-if="!isCurrentWeek"
                    variant="ghost"
                    size="sm"
                    as-child
                >
                    <Link :href="weekOf(employee.id, today)">This week</Link>
                </Button>
                <span class="ml-2 text-sm text-muted-foreground"
                    >Total
                    <span class="font-medium text-foreground tabular-nums">{{
                        formatHours(total)
                    }}</span></span
                >
            </div>
        </div>

        <ul class="divide-y rounded-lg border">
            <li v-for="day in days" :key="day.date" class="space-y-2 px-4 py-3">
                <div class="flex items-center justify-between gap-4">
                    <p class="font-medium">
                        {{ formatDay(day.date) }}
                        <span class="ml-2 text-sm text-muted-foreground">{{
                            formatHours(day.hours)
                        }}</span>
                        <span
                            v-if="day.time_off"
                            class="ml-2 rounded-full bg-muted px-2 py-0.5 text-xs font-medium"
                            >{{
                                day.time_off === 'paid'
                                    ? 'Paid Time Off'
                                    : 'Unpaid Time Off'
                            }}</span
                        >
                        <span
                            v-else-if="day.nothing_logged"
                            class="ml-2 text-xs text-muted-foreground italic"
                            >Nothing logged</span
                        >
                    </p>
                    <WorkEntryDialog
                        v-if="canManage && day.date <= today"
                        :employee="employee"
                        :assignments="assignments"
                        :date="day.date"
                        :today="today"
                    />
                </div>
                <ul v-if="day.entries.length > 0" class="space-y-1">
                    <li
                        v-for="entry in day.entries"
                        :key="entry.id"
                        class="flex items-center justify-between gap-4 text-sm"
                    >
                        <p>
                            <span class="font-medium">{{ entry.project }}</span>
                            <span class="text-muted-foreground">
                                · {{ entry.description }}</span
                            >
                            <span
                                v-if="entry.approved"
                                class="block text-xs text-muted-foreground"
                                >{{ signOffs(entry) }}</span
                            >
                        </p>
                        <div class="flex items-center gap-2">
                            <StatusBadge :state="entry.state" />
                            <span class="tabular-nums">{{
                                formatHours(entry.hours)
                            }}</span>
                            <WorkEntryDialog
                                v-if="entry.can_edit"
                                :employee="employee"
                                :assignments="assignments"
                                :date="day.date"
                                :today="today"
                                :entry="entry"
                            />
                            <DeleteWorkEntry
                                v-else-if="entry.can_delete"
                                :entry="entry"
                            />
                        </div>
                    </li>
                </ul>
            </li>
        </ul>

        <section v-if="assignmentTotals.length > 0" class="space-y-2">
            <h2 class="text-sm font-medium">This week</h2>
            <ul class="divide-y rounded-lg border text-sm">
                <li
                    v-for="assignmentTotal in assignmentTotals"
                    :key="assignmentTotal.assignment_id"
                    class="flex justify-between px-4 py-2"
                >
                    <span>{{ assignmentTotal.project }}</span>
                    <span class="tabular-nums">{{
                        formatHours(assignmentTotal.hours)
                    }}</span>
                </li>
            </ul>
        </section>
    </div>
</template>

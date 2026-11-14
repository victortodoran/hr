<script setup lang="ts">
import { formatHours } from '@/lib/work-entries';
import { ArrowRight } from '@lucide/vue';
import { nextTick, onMounted, ref } from 'vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import CopyWorkEntriesButton from '@/components/work-entries/CopyWorkEntriesButton.vue';
import DeleteWorkEntry from '@/components/work-entries/DeleteWorkEntry.vue';
import WorkEntryRow from '@/components/work-entries/WorkEntryRow.vue';
import { formatLongDay, formatWeekdayAndDay } from '@/lib/dates';
import { timeOffKindLabel } from '@/lib/time-off';
import type {
    Choice,
    WorkEntryAssignment,
    WorkEntryDay,
    WorkEntryPeriod,
} from '@/types';

/**
 * One Calendar day opened up: every Work Entry on it, with a bin on those
 * the viewer may delete, and, on a day hours can be added to, an add form,
 * copying from the latest earlier day and a way on to the next empty day.
 */
const props = defineProps<{
    day: WorkEntryDay;
    employee: Choice;
    period: WorkEntryPeriod;
    today: string;
    assignments: WorkEntryAssignment[];
    /** Whether the viewer can add hours to this day. */
    takesEntries: boolean;
    /** The next weekday of the month with nothing on it to add to, if any. */
    nextEmptyDay: string | null;
    /** The Assignment of the Work Entry last added on this page. */
    lastAssignmentId: number | null;
}>();

const emit = defineEmits<{
    /** A Work Entry was added, on this Assignment. */
    added: [assignmentId: number];
    /** Open another day instead. */
    open: [date: string];
    /** Whether the add form on this day holds typing that isn't saved yet. */
    dirty: [date: string, isDirty: boolean];
    /** Esc was pressed in the add form. */
    close: [];
}>();

const addRow = ref<InstanceType<typeof WorkEntryRow> | null>(null);

onMounted(() => addRow.value?.focusDescription());

async function entryAdded(assignmentId: number): Promise<void> {
    emit('added', assignmentId);
    await nextTick();
    addRow.value?.focusDescription();
}
</script>

<template>
    <div class="flex flex-col gap-3 text-sm" :data-panel-date="day.date">
        <div>
            <p class="font-medium">{{ formatLongDay(day.date) }}</p>
            <p class="text-xs text-muted-foreground tabular-nums">
                {{ formatHours(day.hours) }} logged
            </p>
        </div>

        <ul v-if="day.entries.length > 0" class="divide-y rounded-md border">
            <li
                v-for="entry in day.entries"
                :key="entry.id"
                class="flex items-center gap-2 px-3 py-1.5"
            >
                <span
                    class="min-w-0 flex-1 truncate"
                    :title="`${entry.project} · ${entry.description}`"
                    >{{ entry.description }}</span
                >
                <StatusBadge :state="entry.state" />
                <span class="w-12 text-right font-medium tabular-nums">{{
                    formatHours(entry.hours)
                }}</span>
                <DeleteWorkEntry
                    v-if="entry.can_delete"
                    :entry="entry"
                    :period-query="period.query"
                />
            </li>
        </ul>

        <p v-if="day.time_off" class="text-muted-foreground">
            {{ timeOffKindLabel(day.time_off) }}.<template v-if="!takesEntries">
                No hours to log.</template
            >
        </p>
        <p
            v-else-if="day.date > today && day.entries.length === 0"
            class="text-muted-foreground"
        >
            Hours can't be logged on days still to come.
        </p>
        <p
            v-else-if="!takesEntries && day.entries.length === 0"
            class="text-muted-foreground"
        >
            Nothing logged.
        </p>

        <WorkEntryRow
            v-if="takesEntries"
            ref="addRow"
            compact
            :employee="employee"
            :period-query="period.query"
            :assignments="assignments"
            :date="day.date"
            :today="today"
            :last-assignment-id="lastAssignmentId"
            @saved="entryAdded"
            @dirty="emit('dirty', day.date, $event)"
            @dismiss="emit('close')"
        />

        <div
            v-if="takesEntries && (day.copy_from || nextEmptyDay)"
            class="flex flex-wrap items-start justify-between gap-2 border-t pt-3"
        >
            <CopyWorkEntriesButton
                v-if="day.copy_from"
                class="-ml-2"
                :employee="employee"
                :period-query="period.query"
                :date="day.date"
                :copy-from="day.copy_from"
            />
            <span v-else />
            <Button
                v-if="nextEmptyDay"
                type="button"
                variant="outline"
                size="sm"
                class="h-7"
                @click="emit('open', nextEmptyDay)"
                >Next empty day · {{ formatWeekdayAndDay(nextEmptyDay)
                }}<ArrowRight
            /></Button>
        </div>
    </div>
</template>

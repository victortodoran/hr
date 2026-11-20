<script setup lang="ts">
import { ChevronLeft, ChevronRight, X } from '@lucide/vue';
import { nextTick, onMounted, ref } from 'vue';
import Callout from '@/components/Callout.vue';
import IconButton from '@/components/IconButton.vue';
import RowActions from '@/components/RowActions.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    DropdownMenuItem,
    DropdownMenuSeparator,
} from '@/components/ui/dropdown-menu';
import CopyWorkEntriesButton from '@/components/work-entries/CopyWorkEntriesButton.vue';
import DeleteWorkEntry from '@/components/work-entries/DeleteWorkEntry.vue';
import WorkEntryRow from '@/components/work-entries/WorkEntryRow.vue';
import { formatLongDay } from '@/lib/dates';
import { timeOffKindLabel } from '@/lib/time-off';
import { formatHours } from '@/lib/work-entries';
import type {
    Choice,
    Period,
    WorkEntryAssignment,
    WorkEntryDay,
    WorkEntryRecord,
} from '@/types';

/**
 * One Calendar day opened up (WorkDayPanel): its date with ‹ › to step to
 * the day before or after and ✕ to close, every Work Entry on it with its
 * state and hours and a ⋯ menu to Edit or Delete it, a Declined one's
 * reason in a Callout with Edit, and on a day hours can be added to, a
 * labelled add form (rule V11 preselects the Assignment) that ↵ submits
 * and keeps open, with copying the previous day's entries. In a bottom
 * sheet on a phone (P7) its buttons stick to the bottom.
 */
const props = defineProps<{
    day: WorkEntryDay;
    employee: Choice;
    period: Period;
    today: string;
    assignments: WorkEntryAssignment[];
    /** Whether the viewer can add hours to this day. */
    takesEntries: boolean;
    /** The day ‹ opens, if there is one in the month shown. */
    previousDay: string | null;
    /** The day › opens, if there is one in the month shown. */
    nextDay: string | null;
    /** The Assignment of the Work Entry last added on this page. */
    lastAssignmentId: number | null;
    /** Whether the panel sits in a sheet, which brings its own close button. */
    inSheet?: boolean;
}>();

const emit = defineEmits<{
    /** A Work Entry was added, on this Assignment. */
    added: [assignmentId: number];
    /** Open another day instead. */
    open: [date: string];
    /** Whether a form on this day holds typing that isn't saved yet. */
    dirty: [date: string, isDirty: boolean];
    /** Close the panel. */
    close: [];
}>();

const addRow = ref<InstanceType<typeof WorkEntryRow> | null>(null);

/** The Work Entry being changed in place, if any. */
const editing = ref<number | null>(null);

/** The Work Entry whose Delete is being confirmed, if any. */
const deleting = ref<WorkEntryRecord | null>(null);

onMounted(() => addRow.value?.focusHours());

async function entryAdded(assignmentId: number): Promise<void> {
    emit('added', assignmentId);
    await nextTick();
    addRow.value?.focusHours();
}

/** "1 Work Entry · 5 h", or "Nothing logged". */
function summary(): string {
    const count = props.day.entries.length;

    if (count === 0) {
        return 'Nothing logged';
    }

    return `${count} ${count === 1 ? 'Work Entry' : 'Work Entries'} · ${formatHours(props.day.hours)}`;
}
</script>

<template>
    <div class="flex flex-col gap-3 text-body" :data-panel-date="day.date">
        <div class="flex items-start gap-2" :class="inSheet && 'pr-8'">
            <div class="min-w-0 flex-1">
                <h3 class="flex items-center gap-2 text-section">
                    {{ formatLongDay(day.date) }}
                    <Badge v-if="day.date === today" variant="secondary"
                        >Today</Badge
                    >
                </h3>
                <p class="text-caption text-muted-foreground tabular-nums">
                    {{ summary() }}
                </p>
            </div>
            <div class="flex items-center">
                <IconButton
                    label="Previous day"
                    :disabled="previousDay === null"
                    @click="previousDay && emit('open', previousDay)"
                    ><ChevronLeft
                /></IconButton>
                <IconButton
                    label="Next day"
                    :disabled="nextDay === null"
                    @click="nextDay && emit('open', nextDay)"
                    ><ChevronRight
                /></IconButton>
                <IconButton v-if="!inSheet" label="Close" @click="emit('close')"
                    ><X
                /></IconButton>
            </div>
        </div>

        <ul v-if="day.entries.length > 0" class="flex flex-col">
            <li
                v-for="entry in day.entries"
                :key="entry.id"
                class="flex flex-col gap-2 border-b py-2.5 first:border-t"
                :data-state="entry.state"
            >
                <WorkEntryRow
                    v-if="editing === entry.id"
                    compact
                    :employee="employee"
                    :period-query="period.query"
                    :assignments="assignments"
                    :date="day.date"
                    :today="today"
                    :entry="entry"
                    @saved="editing = null"
                    @cancel="editing = null"
                    @dismiss="editing = null"
                    @dirty="emit('dirty', day.date, $event)"
                />
                <template v-else>
                    <div class="flex items-center gap-2.5">
                        <div class="min-w-0 flex-1">
                            <p
                                class="truncate font-medium"
                                :class="
                                    entry.declined &&
                                    'text-muted-foreground line-through'
                                "
                            >
                                {{ entry.description }}
                            </p>
                            <p
                                class="mt-0.5 flex flex-wrap items-center gap-2 text-meta text-muted-foreground"
                            >
                                {{ entry.project }}
                                <StatusBadge :state="entry.state" />
                            </p>
                        </div>
                        <span
                            class="font-semibold tabular-nums"
                            :class="
                                entry.declined &&
                                'text-muted-foreground line-through'
                            "
                            >{{ formatHours(entry.hours) }}</span
                        >
                        <RowActions
                            v-if="entry.can_edit || entry.can_delete"
                            :label="entry.description"
                            class="sm:opacity-100"
                        >
                            <template #menu>
                                <DropdownMenuItem
                                    v-if="entry.can_edit"
                                    @select="editing = entry.id"
                                    >Edit</DropdownMenuItem
                                >
                                <template v-if="entry.can_delete">
                                    <DropdownMenuSeparator
                                        v-if="entry.can_edit"
                                    />
                                    <DropdownMenuItem
                                        variant="destructive"
                                        @select="deleting = entry"
                                        >Delete</DropdownMenuItem
                                    >
                                </template>
                            </template>
                        </RowActions>
                    </div>
                    <Callout v-if="entry.declined" tone="declined">
                        Declined by {{ entry.declined.by }}:
                        {{ entry.declined.reason }}
                        <template v-if="entry.can_edit" #action>
                            <Button
                                type="button"
                                variant="secondary"
                                size="sm"
                                @click="editing = entry.id"
                                >Edit</Button
                            >
                        </template>
                    </Callout>
                </template>
            </li>
        </ul>

        <DeleteWorkEntry
            v-if="deleting"
            :entry="deleting"
            :period-query="period.query"
            without-trigger
            :open="deleting !== null"
            @update:open="(open) => !open && (deleting = null)"
        />

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

        <template v-if="takesEntries && editing === null">
            <h4 class="text-section sm:hidden">Add a Work Entry</h4>
            <WorkEntryRow
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
            >
                <template v-if="day.copy_from" #footer>
                    <CopyWorkEntriesButton
                        class="-ml-2.5"
                        :employee="employee"
                        :period-query="period.query"
                        :date="day.date"
                        :copy-from="day.copy_from"
                    />
                </template>
            </WorkEntryRow>
        </template>
    </div>
</template>

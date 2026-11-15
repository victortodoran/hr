<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { X } from '@lucide/vue';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import WorkEntryController from '@/actions/App/Http/Controllers/WorkEntryController';
import InputError from '@/components/InputError.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import DeleteWorkEntry from '@/components/work-entries/DeleteWorkEntry.vue';
import type { Choice, WorkEntryAssignment, WorkEntryRecord } from '@/types';

/**
 * A Work Entry typed straight into the Period: an empty row under a day that
 * adds one, or an existing Work Entry turned into fields to change it.
 */
const props = defineProps<{
    /** The Employee whose hours these are. */
    employee: Choice;
    /** The URL query asking for the Period shown, so saving returns to it. */
    periodQuery: Record<string, string>;
    /** The Employee's Assignments that run during the Period. */
    assignments: WorkEntryAssignment[];
    /** The day the row sits under. */
    date: string;
    today: string;
    /** The Work Entry to change; the row adds a new one without it. */
    entry?: WorkEntryRecord;
    /** The Assignment last added on this page, picked when it runs on the day. */
    lastAssignmentId?: number | null;
    /** Lays a new row out for a narrow panel: Description above the rest, with no close button. */
    compact?: boolean;
}>();

const emit = defineEmits<{
    /** The Work Entry was saved, on this Assignment. */
    saved: [assignmentId: number];
    /** Changing or adding the Work Entry was given up. */
    cancel: [];
    /** Esc was pressed, asking to give it up. */
    dismiss: [];
    /** Whether the row holds typing that isn't saved yet. */
    dirty: [isDirty: boolean];
}>();

const form = useForm({
    assignment_id: props.entry ? String(props.entry.assignment_id) : '',
    date: props.date,
    hours: props.entry ? String(props.entry.hours) : '',
    description: props.entry?.description ?? '',
});

const descriptionInput = ref<InstanceType<typeof Input> | null>(null);

/** Whether the Assignment was picked by hand, so it's no longer pre-picked. */
const assignmentPickedByHand = ref(false);

const fieldId = (field: string): string =>
    props.entry
        ? `work-entry-${field}-${props.entry.id}`
        : `work-entry-${field}-${props.date}`;

/**
 * The Assignments running on the chosen day, so only those that would be
 * accepted are offered.
 */
const runningAssignments = computed(() =>
    props.assignments.filter(
        (assignment) =>
            assignment.start_date <= form.date &&
            (assignment.end_date === null || assignment.end_date >= form.date),
    ),
);

/**
 * The Assignment a new row starts with: the only one running on the day,
 * else the one last added if it runs then, else none.
 */
const preferredAssignmentId = computed((): string => {
    const running = runningAssignments.value;

    if (running.length === 1) {
        return String(running[0].id);
    }

    const last = running.find(
        (assignment) => assignment.id === props.lastAssignmentId,
    );

    return last ? String(last.id) : '';
});

watch(
    preferredAssignmentId,
    (preferred) => {
        if (!props.entry && !assignmentPickedByHand.value) {
            form.assignment_id = preferred;
        }
    },
    { immediate: true },
);

const isDirty = computed((): boolean =>
    props.entry ? form.isDirty : form.hours !== '' || form.description !== '',
);

watch(isDirty, (dirty) => emit('dirty', dirty), { immediate: true });

/** A row that goes away, such as a deleted Work Entry's, holds nothing unsaved. */
onBeforeUnmount(() => emit('dirty', false));

function pickAssignment(assignmentId: unknown): void {
    assignmentPickedByHand.value = true;
    form.assignment_id = String(assignmentId);
}

function save(): void {
    const route = props.entry
        ? WorkEntryController.update(props.entry.id, {
              query: props.periodQuery,
          })
        : WorkEntryController.store(props.employee.id, {
              query: props.periodQuery,
          });

    form.submit(route, {
        preserveScroll: true,
        onSuccess: () => {
            const assignmentId = Number(form.assignment_id);

            if (!props.entry) {
                form.hours = '';
                form.description = '';
                assignmentPickedByHand.value = false;
            }

            emit('saved', assignmentId);
        },
    });
}

/**
 * Puts the cursor in Description, ready for the next Work Entry.
 */
function focusDescription(): void {
    const element = descriptionInput.value?.$el as HTMLInputElement | undefined;
    element?.focus();
}

defineExpose({ focusDescription });
</script>

<template>
    <form
        class="grid gap-2 sm:items-start"
        :class="
            compact
                ? 'grid-cols-[minmax(0,1fr)_5rem_auto] items-start'
                : entry
                  ? 'sm:grid-cols-[9rem_minmax(0,1fr)_12rem_5rem_auto]'
                  : 'sm:grid-cols-[minmax(0,1fr)_12rem_5rem_auto]'
        "
        @submit.prevent="save"
        @keydown.esc.prevent="emit('dismiss')"
    >
        <div v-if="entry" class="grid min-w-0 gap-1">
            <label :for="fieldId('date')" class="sr-only">Date</label>
            <Input
                :id="fieldId('date')"
                v-model="form.date"
                type="date"
                :max="today"
                required
            />
            <InputError :message="form.errors.date" />
        </div>
        <div class="grid min-w-0 gap-1" :class="compact && 'col-span-3'">
            <label :for="fieldId('description')" class="sr-only"
                >Description</label
            >
            <Input
                :id="fieldId('description')"
                ref="descriptionInput"
                v-model="form.description"
                placeholder="Description"
                required
            />
            <InputError :message="form.errors.description" />
        </div>
        <div class="grid min-w-0 gap-1">
            <label :for="fieldId('assignment')" class="sr-only"
                >Assignment</label
            >
            <Select
                :model-value="form.assignment_id"
                @update:model-value="pickAssignment"
            >
                <SelectTrigger
                    :id="fieldId('assignment')"
                    class="w-full min-w-0"
                >
                    <SelectValue
                        placeholder="Assignment"
                        class="min-w-0 truncate"
                    />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem
                        v-for="assignment in runningAssignments"
                        :key="assignment.id"
                        :value="String(assignment.id)"
                    >
                        {{ assignment.project
                        }}<span
                            v-if="assignment.job_title"
                            class="text-muted-foreground"
                        >
                            · {{ assignment.job_title }}</span
                        >
                    </SelectItem>
                </SelectContent>
            </Select>
            <p
                v-if="runningAssignments.length === 0"
                class="text-xs text-muted-foreground"
            >
                No Assignment runs on this day.
            </p>
            <InputError :message="form.errors.assignment_id" />
        </div>
        <div class="grid min-w-0 gap-1">
            <label :for="fieldId('hours')" class="sr-only">Hours</label>
            <Input
                :id="fieldId('hours')"
                v-model="form.hours"
                type="number"
                min="0.25"
                max="24"
                step="0.25"
                placeholder="Hours"
                required
            />
            <InputError :message="form.errors.hours" />
        </div>
        <div class="flex items-center gap-1">
            <SubmitButton :processing="form.processing">{{
                entry ? 'Save' : 'Add'
            }}</SubmitButton>
            <Button
                v-if="!entry && !compact"
                type="button"
                variant="ghost"
                size="icon"
                aria-label="Close"
                @click="emit('cancel')"
                ><X
            /></Button>
            <template v-if="entry">
                <Button type="button" variant="ghost" @click="emit('cancel')"
                    >Cancel</Button
                >
                <DeleteWorkEntry :entry="entry" :period-query="periodQuery" />
            </template>
        </div>
    </form>
</template>

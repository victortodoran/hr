<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { X } from '@lucide/vue';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import WorkEntryController from '@/actions/App/Http/Controllers/WorkEntries/WorkEntryController';
import InputError from '@/components/InputError.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
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
    /**
     * Lays the row out for the day panel: labelled fields, Assignment and
     * Hours above Description, and its buttons in a footer, after the
     * `footer` slot's.
     */
    compact?: boolean;
}>();

defineSlots<{
    /** Buttons before the panel's own, such as copying the previous day. */
    footer?: () => unknown;
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
const hoursInput = ref<InstanceType<typeof Input> | null>(null);

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

/**
 * Puts the cursor in Hours, the day panel's first field to type in once
 * its Assignment is picked.
 */
function focusHours(): void {
    const element = hoursInput.value?.$el as HTMLInputElement | undefined;
    element?.focus();
}

defineExpose({ focusDescription, focusHours });
</script>

<template>
    <form
        class="grid gap-2"
        :class="
            compact
                ? 'grid-cols-[minmax(0,1fr)_5.5rem] gap-x-2 gap-y-3'
                : entry
                  ? 'sm:grid-cols-[9rem_minmax(0,1fr)_12rem_5rem_auto] sm:items-start'
                  : 'sm:grid-cols-[minmax(0,1fr)_12rem_5rem_auto] sm:items-start'
        "
        @submit.prevent="save"
        @keydown.esc.prevent="emit('dismiss')"
    >
        <div v-if="entry && !compact" class="grid min-w-0 gap-1">
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
        <div
            class="grid min-w-0 gap-1.5"
            :class="compact && 'order-3 col-span-2'"
        >
            <Label
                :for="fieldId('description')"
                :class="compact ? '' : 'sr-only'"
                >Description</Label
            >
            <Input
                :id="fieldId('description')"
                ref="descriptionInput"
                v-model="form.description"
                :placeholder="
                    compact ? 'e.g. Release checklist' : 'Description'
                "
                required
            />
            <InputError :message="form.errors.description" />
        </div>
        <div class="grid min-w-0 gap-1.5" :class="compact && 'order-1'">
            <Label
                :for="fieldId('assignment')"
                :class="compact ? '' : 'sr-only'"
                >Assignment</Label
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
                class="text-caption text-muted-foreground"
            >
                No Assignment runs on this day.
            </p>
            <InputError :message="form.errors.assignment_id" />
        </div>
        <div class="grid min-w-0 gap-1.5" :class="compact && 'order-2'">
            <Label :for="fieldId('hours')" :class="compact ? '' : 'sr-only'"
                >Hours</Label
            >
            <Input
                :id="fieldId('hours')"
                ref="hoursInput"
                v-model="form.hours"
                type="number"
                min="0.25"
                max="24"
                step="0.25"
                :placeholder="compact ? '' : 'Hours'"
                class="tabular-nums"
                required
            />
            <InputError :message="form.errors.hours" />
        </div>
        <p
            v-if="entry?.state === 'declined'"
            class="text-meta text-muted-foreground"
            :class="
                compact
                    ? 'order-4 col-span-2'
                    : 'sm:order-first sm:col-span-full'
            "
        >
            Saving sends it back to Pending, for approval again.
        </p>
        <div
            v-if="compact"
            class="order-5 col-span-2 flex flex-wrap items-center gap-2 max-sm:sticky max-sm:bottom-0 max-sm:-mx-4 max-sm:border-t max-sm:bg-background max-sm:px-4 max-sm:py-3"
        >
            <slot name="footer" />
            <div class="ml-auto flex items-center gap-2 max-sm:w-full">
                <Button
                    v-if="entry"
                    type="button"
                    variant="ghost"
                    @click="emit('cancel')"
                    >Cancel</Button
                >
                <SubmitButton
                    :processing="form.processing"
                    class="max-sm:flex-1"
                >
                    <template v-if="entry">Save</template>
                    <template v-else
                        >Add Work Entry<kbd
                            class="ml-1 rounded border border-primary-foreground/40 px-1 text-caption max-sm:hidden"
                            aria-hidden="true"
                            >↵</kbd
                        ></template
                    >
                </SubmitButton>
            </div>
        </div>
        <div v-else class="flex items-center gap-1">
            <SubmitButton :processing="form.processing">{{
                entry ? 'Save' : 'Add Work Entry'
            }}</SubmitButton>
            <Button
                v-if="!entry"
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

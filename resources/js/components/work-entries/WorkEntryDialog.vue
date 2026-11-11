<script setup lang="ts">
import { Form, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import WorkEntryController from '@/actions/App/Http/Controllers/WorkEntryController';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { Choice, WorkEntryAssignment, WorkEntryRecord } from '@/types';

const props = defineProps<{
    /** The Employee whose hours these are. */
    employee: Choice;
    /** The Employee's Assignments that run during the week. */
    assignments: WorkEntryAssignment[];
    /** The day a new Work Entry starts on. */
    date: string;
    today: string;
    /** The Work Entry to correct; a new one is added without it. */
    entry?: WorkEntryRecord;
}>();

const open = ref(false);
const fieldId = (field: string): string =>
    `work-entry-${field}-${props.entry?.id ?? props.date}`;

const form = useForm({
    assignment_id: '',
    date: '',
    hours: '',
    description: '',
});

watch(open, (isOpen) => {
    if (!isOpen) {
        return;
    }

    form.clearErrors();
    form.assignment_id = props.entry ? String(props.entry.assignment_id) : '';
    form.date = props.date;
    form.hours = props.entry ? String(props.entry.hours) : '';
    form.description = props.entry?.description ?? '';
});

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

function save(): void {
    const route = props.entry
        ? WorkEntryController.update(props.entry.id)
        : WorkEntryController.store(props.employee.id);

    form.submit(route, {
        preserveScroll: true,
        onSuccess: () => {
            open.value = false;
            form.reset();
        },
    });
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogTrigger as-child>
            <Button v-if="entry" variant="ghost" size="sm">Edit</Button>
            <Button v-else variant="outline" size="sm">Add</Button>
        </DialogTrigger>
        <DialogContent>
            <DialogHeader>
                <DialogTitle
                    >{{ entry ? 'Change Work Entry' : 'Add Work Entry' }} for
                    {{ employee.name }}</DialogTitle
                >
                <DialogDescription>
                    Record how many hours {{ employee.name }} worked on one
                    Assignment, in quarter-hour steps, and what was done.
                </DialogDescription>
            </DialogHeader>

            <form class="space-y-4" @submit.prevent="save">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label :for="fieldId('date')">Date</Label>
                        <Input
                            :id="fieldId('date')"
                            v-model="form.date"
                            type="date"
                            :max="today"
                            required
                        />
                        <InputError :message="form.errors.date" />
                    </div>
                    <div class="grid gap-2">
                        <Label :for="fieldId('hours')">Hours</Label>
                        <Input
                            :id="fieldId('hours')"
                            v-model="form.hours"
                            type="number"
                            min="0.25"
                            max="24"
                            step="0.25"
                            placeholder="e.g. 7.5"
                            required
                        />
                        <InputError :message="form.errors.hours" />
                    </div>
                </div>
                <div class="grid gap-2">
                    <Label :for="fieldId('assignment')">Assignment</Label>
                    <Select v-model="form.assignment_id">
                        <SelectTrigger
                            :id="fieldId('assignment')"
                            class="w-full"
                        >
                            <SelectValue placeholder="Choose an Assignment" />
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
                        v-if="form.date && runningAssignments.length === 0"
                        class="text-sm text-muted-foreground"
                    >
                        No Assignment runs on this day.
                    </p>
                    <InputError :message="form.errors.assignment_id" />
                </div>
                <div class="grid gap-2">
                    <Label :for="fieldId('description')">What was done</Label>
                    <Input
                        :id="fieldId('description')"
                        v-model="form.description"
                        placeholder="e.g. Reviewing the API"
                        required
                    />
                    <InputError :message="form.errors.description" />
                </div>

                <DialogFooter class="gap-2">
                    <Form
                        v-if="entry"
                        v-bind="WorkEntryController.destroy.form(entry.id)"
                        :options="{ preserveScroll: true }"
                        class="mr-auto"
                        v-slot="{ processing }"
                    >
                        <Button
                            type="submit"
                            variant="ghost"
                            class="text-destructive"
                            :disabled="processing"
                            >Delete</Button
                        >
                    </Form>
                    <DialogClose as-child>
                        <Button variant="secondary" type="button"
                            >Cancel</Button
                        >
                    </DialogClose>
                    <Button type="submit" :disabled="form.processing">{{
                        entry ? 'Save' : 'Add'
                    }}</Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>

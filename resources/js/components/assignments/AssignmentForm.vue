<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import AssignmentController from '@/actions/App/Http/Controllers/Assignments/AssignmentController';
import DatePicker from '@/components/DatePicker.vue';
import FormSheet from '@/components/FormSheet.vue';
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
import { formatPeriod } from '@/lib/dates';
import type { AssignmentRecord, Choice, OpenProject } from '@/types';

/** The Internal Assignment's dates follow the Employee's, so its form leaves them out. */
const props = defineProps<{
    employee: Choice;
    projects: OpenProject[];
    assignment?: AssignmentRecord | null;
}>();

const open = defineModel<boolean>('open', { required: true });

const FORM_ID = 'assignment-form';

const isInternal = computed(
    () => props.assignment?.project.is_internal ?? false,
);

const fieldId = (field: string): string =>
    `assignment-${field}-${props.assignment?.id ?? 'new'}`;

const title = computed(() => {
    if (!props.assignment) {
        return 'Add Assignment';
    }

    return isInternal.value
        ? 'Edit Internal Assignment'
        : `Edit Assignment to ${props.assignment.project.name}`;
});

const description = computed(() => {
    if (!props.assignment) {
        return `${props.employee.name} works on the Project from the start date, for the Capacity set.`;
    }

    return isInternal.value
        ? `The internal Job Title is what the directory shows for ${props.employee.name}. Its dates always follow their Start Date and Leaving Date.`
        : 'Set an end date to end the Assignment early. Pay set to take effect after the end date is removed.';
});

const chosenProject = computed(() =>
    props.assignment
        ? null
        : (props.projects.find(
              (project) => String(project.id) === form.project_id,
          ) ?? null),
);

function saved() {
    return {
        project_id: '',
        job_title: props.assignment?.job_title ?? '',
        start_date: props.assignment?.start_date ?? '',
        end_date: props.assignment?.end_date ?? '',
        capacity: String(props.assignment?.capacity ?? ''),
    };
}

const form = useForm(saved());

/** A new Assignment keeps what was typed, so closing by accident loses nothing; nothing resets mid-save. */
let lastOpenedFor: number | null = null;

watch(open, (isOpen) => {
    if (!isOpen || form.processing) {
        return;
    }

    const openedFor = props.assignment?.id ?? null;

    if (props.assignment || lastOpenedFor !== openedFor) {
        form.defaults(saved());
        form.reset();
        form.clearErrors();
    }

    lastOpenedFor = openedFor;
});

function submit(): void {
    const action = props.assignment
        ? AssignmentController.update(props.assignment.id)
        : AssignmentController.store(props.employee.id);
    const editingInternal = isInternal.value;
    const editing = Boolean(props.assignment);

    form.transform(
        ({ project_id, job_title, start_date, end_date, capacity }) => ({
            job_title: job_title === '' ? null : job_title,
            capacity: capacity === '' ? null : Number(capacity),
            ...(editingInternal
                ? {}
                : { start_date, end_date: end_date === '' ? null : end_date }),
            ...(editing ? {} : { project_id: Number(project_id) }),
        }),
    ).submit(action, {
        preserveScroll: true,
        onSuccess: () => {
            open.value = false;

            if (!editing) {
                form.reset();
            }
        },
    });
}
</script>

<template>
    <FormSheet v-model:open="open" :title="title" :description="description">
        <form
            :id="FORM_ID"
            class="flex flex-col gap-5"
            @submit.prevent="submit"
        >
            <div v-if="!assignment" class="grid gap-2">
                <Label :for="fieldId('project')">Project</Label>
                <Select v-model="form.project_id" name="project_id" required>
                    <SelectTrigger :id="fieldId('project')" class="w-full">
                        <SelectValue placeholder="Choose a Project" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="project in projects"
                            :key="project.id"
                            :value="String(project.id)"
                        >
                            {{ project.name }}
                            <span class="text-muted-foreground">
                                · {{ project.client ?? 'No Client' }} ·
                                {{
                                    formatPeriod(
                                        project.start_date,
                                        project.end_date,
                                    )
                                }}
                            </span>
                        </SelectItem>
                    </SelectContent>
                </Select>
                <InputError :message="form.errors.project_id" />
            </div>

            <div class="grid gap-2">
                <Label :for="fieldId('job-title')">{{
                    isInternal ? 'Internal Job Title (optional)' : 'Job Title'
                }}</Label>
                <Input
                    :id="fieldId('job-title')"
                    v-model="form.job_title"
                    placeholder="e.g. Senior Developer"
                    :required="!isInternal"
                />
                <InputError :message="form.errors.job_title" />
            </div>

            <div v-if="!isInternal" class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label :for="fieldId('start')">Start date</Label>
                    <DatePicker
                        :id="fieldId('start')"
                        v-model="form.start_date"
                        :min="chosenProject?.start_date"
                        :max="chosenProject?.end_date ?? undefined"
                        required
                        :invalid="Boolean(form.errors.start_date)"
                    />
                    <InputError :message="form.errors.start_date" />
                </div>
                <div class="grid gap-2">
                    <Label :for="fieldId('end')">End date (optional)</Label>
                    <DatePicker
                        :id="fieldId('end')"
                        v-model="form.end_date"
                        :min="form.start_date || chosenProject?.start_date"
                        :max="chosenProject?.end_date ?? undefined"
                        :invalid="Boolean(form.errors.end_date)"
                    />
                    <InputError :message="form.errors.end_date" />
                </div>
            </div>

            <div class="grid gap-2">
                <Label :for="fieldId('capacity')">Capacity (%)</Label>
                <Input
                    :id="fieldId('capacity')"
                    v-model="form.capacity"
                    type="number"
                    min="0"
                    max="100"
                    step="1"
                    required
                    class="tabular-nums sm:w-32"
                />
                <InputError :message="form.errors.capacity" />
            </div>
        </form>

        <template #footer>
            <Button type="button" variant="ghost" @click="open = false"
                >Cancel</Button
            >
            <SubmitButton :form="FORM_ID" :processing="form.processing">{{
                assignment ? 'Save' : 'Add Assignment'
            }}</SubmitButton>
        </template>
    </FormSheet>
</template>

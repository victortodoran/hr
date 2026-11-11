<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import AssignmentController from '@/actions/App/Http/Controllers/AssignmentController';
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
import { formatPeriod } from '@/lib/dates';
import type { AssignmentRecord, Choice, OpenProject } from '@/types';

const props = defineProps<{
    employee: Choice;
    projects: OpenProject[];
    assignment?: AssignmentRecord;
}>();

const open = ref(false);
const isInternal = props.assignment?.project.is_internal ?? false;
const jobTitleLabel = isInternal
    ? 'Internal Job Title (optional)'
    : 'Job Title';
const fieldId = (field: string): string =>
    `assignment-${field}-${props.assignment?.id ?? 'new'}`;

const form = useForm({
    project_id: '',
    job_title: props.assignment?.job_title ?? '',
    start_date: props.assignment?.start_date ?? '',
    end_date: props.assignment?.end_date ?? '',
    capacity: String(props.assignment?.capacity ?? ''),
});

function submit(): void {
    const action = props.assignment
        ? AssignmentController.update(props.assignment.id)
        : AssignmentController.store(props.employee.id);

    form.transform(
        ({ project_id, job_title, start_date, end_date, capacity }) => ({
            job_title: job_title === '' ? null : job_title,
            capacity: capacity === '' ? null : Number(capacity),
            ...(isInternal
                ? {}
                : { start_date, end_date: end_date === '' ? null : end_date }),
            ...(props.assignment ? {} : { project_id: Number(project_id) }),
        }),
    ).submit(action, {
        preserveScroll: true,
        onSuccess: () => {
            open.value = false;

            if (!props.assignment) {
                form.reset();
            }
        },
    });
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogTrigger as-child>
            <Button v-if="assignment" variant="outline" size="sm">Edit</Button>
            <Button v-else>Assign to a Project</Button>
        </DialogTrigger>
        <DialogContent>
            <form class="space-y-6" @submit.prevent="submit">
                <DialogHeader>
                    <DialogTitle>{{
                        assignment
                            ? isInternal
                                ? 'Edit Internal Assignment'
                                : `Edit Assignment to ${assignment.project.name}`
                            : `Assign ${employee.name} to a Project`
                    }}</DialogTitle>
                    <DialogDescription v-if="isInternal">
                        The internal Job Title is what the directory shows for
                        {{ employee.name }}. Its dates always follow their Start
                        Date and Leaving Date. Its Capacity is set by hand, like
                        any other Assignment's.
                    </DialogDescription>
                    <DialogDescription v-else-if="assignment">
                        Set an end date to end the Assignment early.
                    </DialogDescription>
                </DialogHeader>

                <div v-if="!assignment" class="grid gap-2">
                    <Label :for="fieldId('project')">Project</Label>
                    <Select v-model="form.project_id">
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
                        jobTitleLabel
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
                        <Input
                            :id="fieldId('start')"
                            v-model="form.start_date"
                            type="date"
                            required
                        />
                        <InputError :message="form.errors.start_date" />
                    </div>
                    <div class="grid gap-2">
                        <Label :for="fieldId('end')">End date (optional)</Label>
                        <Input
                            :id="fieldId('end')"
                            v-model="form.end_date"
                            type="date"
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
                    />
                    <InputError :message="form.errors.capacity" />
                </div>

                <DialogFooter class="gap-2">
                    <DialogClose as-child>
                        <Button variant="secondary" type="button"
                            >Cancel</Button
                        >
                    </DialogClose>
                    <Button type="submit" :disabled="form.processing"
                        >Save</Button
                    >
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>

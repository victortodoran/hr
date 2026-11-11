<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import ProjectController from '@/actions/App/Http/Controllers/ProjectController';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
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
import type { Choice, ProjectRecord } from '@/types';

const props = defineProps<{
    clients: Choice[];
    project?: ProjectRecord;
}>();

/**
 * The Select can't hold null, so "no Client" travels as this value.
 */
const noClient = 'none';

const open = ref(false);
const fieldId = (field: string): string =>
    `project-${field}-${props.project?.id ?? 'new'}`;

const form = useForm({
    name: props.project?.name ?? '',
    client_id: props.project?.client
        ? String(props.project.client.id)
        : noClient,
    start_date: props.project?.start_date ?? '',
    end_date: props.project?.end_date ?? '',
});

function submit(): void {
    const action = props.project
        ? ProjectController.update(props.project.id)
        : ProjectController.store();

    form.transform(({ client_id, end_date, ...data }) => ({
        ...data,
        client_id: client_id === noClient ? null : Number(client_id),
        end_date: end_date === '' ? null : end_date,
    })).submit(action, {
        preserveScroll: true,
        onSuccess: () => {
            open.value = false;

            if (!props.project) {
                form.reset();
            }
        },
    });
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogTrigger as-child>
            <Button v-if="project" variant="outline" size="sm">Edit</Button>
            <Button v-else>New Project</Button>
        </DialogTrigger>
        <DialogContent>
            <form class="space-y-6" @submit.prevent="submit">
                <DialogHeader>
                    <DialogTitle>{{
                        project ? `Edit ${project.name}` : 'New Project'
                    }}</DialogTitle>
                </DialogHeader>

                <div class="grid gap-2">
                    <Label :for="fieldId('name')">Name</Label>
                    <Input
                        :id="fieldId('name')"
                        v-model="form.name"
                        placeholder="e.g. Website relaunch"
                        required
                    />
                    <InputError :message="form.errors.name" />
                </div>

                <div class="grid gap-2">
                    <Label :for="fieldId('client')">Client</Label>
                    <Select v-model="form.client_id">
                        <SelectTrigger :id="fieldId('client')" class="w-full">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem :value="noClient">No Client</SelectItem>
                            <SelectItem
                                v-for="client in clients"
                                :key="client.id"
                                :value="String(client.id)"
                            >
                                {{ client.name }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="form.errors.client_id" />
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
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

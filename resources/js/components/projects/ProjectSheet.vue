<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import ProjectController from '@/actions/App/Http/Controllers/Projects/ProjectController';
import FormSheet from '@/components/FormSheet.vue';
import ProjectFields from '@/components/projects/ProjectFields.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import { Button } from '@/components/ui/button';
import { projectFields, projectPayload } from '@/lib/projects';
import type { Choice, ProjectRecord } from '@/types';

const props = defineProps<{
    project?: ProjectRecord;
    clients: Choice[];
}>();

const open = defineModel<boolean>('open', { required: true });

const FORM_ID = 'project-form';

const isEdit = computed(() => props.project !== undefined);

const form = useForm(projectFields(props.project));

watch(open, (isOpen) => {
    if (isOpen) {
        form.defaults(projectFields(props.project));
        form.reset();
        form.clearErrors();
    }
});

function save(): void {
    if (!props.project) {
        form.transform(projectPayload).submit(ProjectController.store());

        return;
    }

    form.transform(projectPayload).submit(
        ProjectController.update(props.project.id),
        {
            preserveScroll: true,
            onSuccess: () => (open.value = false),
        },
    );
}
</script>

<template>
    <FormSheet
        v-model:open="open"
        :title="isEdit ? 'Edit Project' : 'New Project'"
        description="Employees can be assigned to it while it runs."
    >
        <form :id="FORM_ID" class="flex flex-col gap-5" @submit.prevent="save">
            <ProjectFields
                v-model:name="form.name"
                v-model:client-id="form.client_id"
                v-model:start-date="form.start_date"
                v-model:end-date="form.end_date"
                :clients="clients"
                :errors="form.errors"
            />
        </form>

        <template #footer>
            <Button type="button" variant="ghost" @click="open = false"
                >Cancel</Button
            >
            <SubmitButton :form="FORM_ID" :processing="form.processing">{{
                isEdit ? 'Save Project' : 'New Project'
            }}</SubmitButton>
        </template>
    </FormSheet>
</template>

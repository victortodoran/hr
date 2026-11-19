<script setup lang="ts">
import { Head, setLayoutProps, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import ProjectController from '@/actions/App/Http/Controllers/Projects/ProjectController';
import TeamTable from '@/components/assignments/TeamTable.vue';
import ConfirmDeleteDialog from '@/components/ConfirmDeleteDialog.vue';
import Heading from '@/components/Heading.vue';
import PageContainer from '@/components/PageContainer.vue';
import ProjectFields from '@/components/projects/ProjectFields.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { formatPeriod } from '@/lib/dates';
import { projectFields, projectPayload } from '@/lib/projects';
import { index, show } from '@/routes/projects';
import type { Choice, ProjectRecord, TeamAssignment } from '@/types';

/**
 * A Project and its team. Whoever manages Projects changes its name,
 * Client and dates here, in place, or deletes it; the Internal Project
 * stays as it is.
 */
const props = defineProps<{
    project: ProjectRecord;
    team: TeamAssignment[];
    /** Whether the viewer may change or delete the Project. */
    canManage: boolean;
    /** The Clients it can be for, when the viewer may change it. */
    clients: Choice[];
}>();

/**
 * Who the Project is for and when it runs, in one line.
 */
function summary(): string {
    const forWhom = props.project.client?.name ?? 'No Client';

    if (props.project.is_internal || !props.project.start_date) {
        return `${forWhom} · Always running`;
    }

    return `${forWhom} · ${formatPeriod(props.project.start_date, props.project.end_date)}`;
}

/** Whether the Project's fields show in place of its heading. */
const editing = ref(false);

const form = useForm(projectFields(props.project));

/** Open the fields on the Project as it stands now. */
function startEditing(): void {
    form.defaults(projectFields(props.project));
    form.reset();
    form.clearErrors();
    editing.value = true;
}

/** Put the fields back and close them. */
function cancelEditing(): void {
    form.reset();
    form.clearErrors();
    editing.value = false;
}

/** Save the change and stay here; errors keep the fields open. */
function saveProject(): void {
    form.transform(projectPayload).submit(
        ProjectController.update(props.project.id),
        {
            preserveScroll: true,
            onSuccess: () => (editing.value = false),
        },
    );
}

setLayoutProps({
    breadcrumbs: [
        { title: 'Projects', href: index() },
        { title: props.project.name, href: show(props.project.id) },
    ],
});
</script>

<template>
    <Head :title="project.name" />

    <PageContainer width="wide">
        <Heading :title="project.name" :description="summary()">
            <template v-if="canManage && !editing" #actions>
                <Button variant="outline" @click="startEditing">Change</Button>
                <ConfirmDeleteDialog
                    :form="ProjectController.destroy.form(project.id)"
                    :title="`Delete ${project.name}?`"
                    description="This can't be undone."
                >
                    <template #trigger>
                        <Button variant="ghost" class="text-destructive"
                            >Delete</Button
                        >
                    </template>
                </ConfirmDeleteDialog>
            </template>
        </Heading>

        <Card v-if="editing" class="max-w-2xl px-6 py-5">
            <form class="space-y-6" @submit.prevent="saveProject">
                <ProjectFields
                    v-model:name="form.name"
                    v-model:client-id="form.client_id"
                    v-model:start-date="form.start_date"
                    v-model:end-date="form.end_date"
                    :clients="clients"
                    :errors="form.errors"
                />
                <div class="flex gap-2">
                    <SubmitButton :processing="form.processing"
                        >Save Project</SubmitButton
                    >
                    <Button type="button" variant="ghost" @click="cancelEditing"
                        >Cancel</Button
                    >
                </div>
            </form>
        </Card>

        <section class="flex flex-col gap-3">
            <h2 class="text-base font-medium">Team</h2>
            <TeamTable
                :assignments="team"
                empty="Nobody you can see is assigned to this Project."
            />
        </section>
    </PageContainer>
</template>

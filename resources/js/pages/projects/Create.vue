<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import ProjectController from '@/actions/App/Http/Controllers/Projects/ProjectController';
import PageHeader from '@/components/PageHeader.vue';
import PageContainer from '@/components/PageContainer.vue';
import ProjectFields from '@/components/projects/ProjectFields.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import { Button } from '@/components/ui/button';
import { projectFields, projectPayload } from '@/lib/projects';
import { create, index } from '@/routes/projects';
import type { Choice } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Projects', href: index() },
            { title: 'New Project', href: create() },
        ],
    },
});

/**
 * Creating a Project: its name, its Client if it has one, and when it runs.
 * Creating it opens its page; cancelling returns to the Projects.
 */
defineProps<{
    clients: Choice[];
}>();

const form = useForm(projectFields());

function createProject(): void {
    form.transform(projectPayload).submit(ProjectController.store());
}
</script>

<template>
    <Head title="New Project" />

    <PageContainer width="form">
        <PageHeader
            title="New Project"
            description="Employees can be assigned to it while it runs."
        />

        <form class="space-y-6" @submit.prevent="createProject">
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
                    >Create Project</SubmitButton
                >
                <Button variant="secondary" as-child>
                    <Link :href="index()">Cancel</Link>
                </Button>
            </div>
        </form>
    </PageContainer>
</template>

<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import Heading from '@/components/Heading.vue';
import DeleteProject from '@/components/projects/DeleteProject.vue';
import ProjectForm from '@/components/projects/ProjectForm.vue';
import { Badge } from '@/components/ui/badge';
import { index } from '@/routes/projects';
import type { Choice, ProjectRecord } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Projects', href: index() }],
    },
});

defineProps<{
    projects: ProjectRecord[];
    clients: Choice[];
}>();

const page = usePage();
const canManage = computed(() =>
    page.props.auth.can.includes('manage-projects'),
);

/**
 * A date as people read it, such as "5 Jan 2026".
 */
function formatDate(date: string): string {
    return new Date(`${date}T00:00:00`).toLocaleDateString(undefined, {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    });
}
</script>

<template>
    <Head title="Projects" />

    <div class="flex max-w-4xl flex-col gap-6 p-4">
        <div class="flex items-start justify-between gap-4">
            <Heading
                title="Projects"
                description="The work Employees can be assigned to"
            />
            <ProjectForm v-if="canManage" :clients="clients" />
        </div>

        <div class="overflow-x-auto rounded-lg border">
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-left text-muted-foreground">
                    <tr>
                        <th class="px-4 py-2 font-medium">Name</th>
                        <th class="px-4 py-2 font-medium">Client</th>
                        <th class="px-4 py-2 font-medium">Dates</th>
                        <th v-if="canManage" class="px-4 py-2">
                            <span class="sr-only">Actions</span>
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <tr v-for="project in projects" :key="project.id">
                        <td class="px-4 py-2 font-medium">
                            {{ project.name }}
                            <Badge
                                v-if="project.is_internal"
                                variant="secondary"
                                class="ml-2"
                                >Internal Project</Badge
                            >
                        </td>
                        <td class="px-4 py-2">
                            <span v-if="project.client">{{
                                project.client.name
                            }}</span>
                            <span v-else class="text-muted-foreground"
                                >No Client</span
                            >
                        </td>
                        <td class="px-4 py-2">
                            <span
                                v-if="project.is_internal"
                                class="text-muted-foreground"
                                >Always running</span
                            >
                            <template v-else-if="project.start_date">
                                {{ formatDate(project.start_date) }} –
                                {{
                                    project.end_date
                                        ? formatDate(project.end_date)
                                        : 'no end date'
                                }}
                            </template>
                        </td>
                        <td v-if="canManage" class="px-4 py-2">
                            <div
                                v-if="!project.is_internal"
                                class="flex justify-end gap-2"
                            >
                                <ProjectForm
                                    :clients="clients"
                                    :project="project"
                                />
                                <DeleteProject :project="project" />
                            </div>
                        </td>
                    </tr>
                    <tr v-if="projects.length === 0">
                        <td
                            :colspan="canManage ? 4 : 3"
                            class="px-4 py-6 text-center text-muted-foreground"
                        >
                            No Projects yet.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>

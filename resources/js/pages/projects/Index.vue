<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { FolderKanban } from '@lucide/vue';
import { computed } from 'vue';
import ProjectController from '@/actions/App/Http/Controllers/ProjectController';
import ConfirmDeleteDialog from '@/components/ConfirmDeleteDialog.vue';
import DataTable from '@/components/DataTable.vue';
import EmptyState from '@/components/EmptyState.vue';
import Heading from '@/components/Heading.vue';
import PageContainer from '@/components/PageContainer.vue';
import ProjectForm from '@/components/projects/ProjectForm.vue';
import { Badge } from '@/components/ui/badge';
import { formatDate } from '@/lib/dates';
import { index, show } from '@/routes/projects';
import type { Choice, ProjectRecord, TableColumn } from '@/types';

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

const columns = computed<TableColumn[]>(() => [
    { key: 'name', label: 'Name' },
    { key: 'client', label: 'Client', minor: true },
    { key: 'dates', label: 'Dates', minor: true },
    ...(canManage.value
        ? [{ key: 'actions', label: 'Actions', align: 'end' as const }]
        : []),
]);
</script>

<template>
    <Head title="Projects" />

    <PageContainer width="wide">
        <div class="flex items-start justify-between gap-4">
            <Heading
                title="Projects"
                description="The work Employees can be assigned to"
            />
            <ProjectForm v-if="canManage" :clients="clients" />
        </div>

        <DataTable :columns="columns" :rows="projects">
            <template #cell-name="{ row }">
                <Link
                    :href="show(row.id)"
                    class="font-medium underline-offset-4 hover:underline"
                    >{{ row.name }}</Link
                >
                <Badge v-if="row.is_internal" variant="secondary" class="ml-2"
                    >Internal Project</Badge
                >
            </template>
            <template #cell-client="{ row }">
                <span v-if="row.client">{{ row.client.name }}</span>
                <span v-else class="text-muted-foreground">No Client</span>
            </template>
            <template #cell-dates="{ row }">
                <span v-if="row.is_internal" class="text-muted-foreground"
                    >Always running</span
                >
                <template v-else-if="row.start_date">
                    {{ formatDate(row.start_date) }} –
                    {{
                        row.end_date ? formatDate(row.end_date) : 'no end date'
                    }}
                </template>
            </template>
            <template #head-actions>
                <span class="sr-only">Actions</span>
            </template>
            <template #cell-actions="{ row }">
                <div v-if="!row.is_internal" class="flex justify-end gap-2">
                    <ProjectForm :clients="clients" :project="row" />
                    <ConfirmDeleteDialog
                        :form="ProjectController.destroy.form(row.id)"
                        :title="`Delete ${row.name}?`"
                        description="This can't be undone."
                    />
                </div>
            </template>
            <template #empty>
                <EmptyState
                    :icon="FolderKanban"
                    message="Projects are the work Employees can be assigned to. There are none yet."
                >
                    <template v-if="canManage" #action>
                        <ProjectForm :clients="clients" />
                    </template>
                </EmptyState>
            </template>
        </DataTable>
    </PageContainer>
</template>

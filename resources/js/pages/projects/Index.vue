<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { FolderKanban } from '@lucide/vue';
import { computed } from 'vue';
import ProjectController from '@/actions/App/Http/Controllers/Projects/ProjectController';
import ConfirmDeleteDialog from '@/components/ConfirmDeleteDialog.vue';
import DataTable from '@/components/DataTable.vue';
import EmptyState from '@/components/EmptyState.vue';
import FilterBar from '@/components/FilterBar.vue';
import FilterSearch from '@/components/FilterSearch.vue';
import Heading from '@/components/Heading.vue';
import PageContainer from '@/components/PageContainer.vue';
import Pagination from '@/components/Pagination.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useListFilters } from '@/composables/useListFilters';
import { formatDate } from '@/lib/dates';
import { create, index, show } from '@/routes/projects';
import type { Paginated, ProjectRecord, TableColumn } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Projects', href: index() }],
    },
});

const props = defineProps<{
    projects: Paginated<ProjectRecord>;
    search: string;
}>();

const filters = useListFilters(index.url, { search: props.search });

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
        <Heading
            title="Projects"
            description="The work Employees can be assigned to"
        >
            <template v-if="canManage" #actions>
                <Button as-child>
                    <Link :href="create()">New Project</Link>
                </Button>
            </template>
        </Heading>

        <FilterBar>
            <FilterSearch v-model="filters.search" />
        </FilterBar>

        <DataTable :columns="columns" :rows="projects.data">
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
                    :message="
                        search
                            ? `Projects are the work Employees can be assigned to. None match “${search}”.`
                            : 'Projects are the work Employees can be assigned to. There are none yet.'
                    "
                >
                    <template v-if="canManage" #action>
                        <Button as-child>
                            <Link :href="create()">New Project</Link>
                        </Button>
                    </template>
                </EmptyState>
            </template>
        </DataTable>

        <Pagination :page="projects" />
    </PageContainer>
</template>

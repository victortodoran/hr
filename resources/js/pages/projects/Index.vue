<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { FolderKanban, Plus } from '@lucide/vue';
import { computed, ref } from 'vue';
import ProjectController from '@/actions/App/Http/Controllers/Projects/ProjectController';
import ConfirmDeleteDialog from '@/components/ConfirmDeleteDialog.vue';
import DataTable from '@/components/DataTable.vue';
import EmptyState from '@/components/EmptyState.vue';
import FilterBar from '@/components/FilterBar.vue';
import FilterSearch from '@/components/FilterSearch.vue';
import PageContainer from '@/components/PageContainer.vue';
import PageHeader from '@/components/PageHeader.vue';
import Pagination from '@/components/Pagination.vue';
import ProjectSheet from '@/components/projects/ProjectSheet.vue';
import RowActions from '@/components/RowActions.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { DropdownMenuItem } from '@/components/ui/dropdown-menu';
import ValueOr from '@/components/ValueOr.vue';
import { useListFilters } from '@/composables/useListFilters';
import { useOverlayRoute } from '@/composables/useOverlayRoute';
import { formatPeriod } from '@/lib/dates';
import { deleteProjectDescription } from '@/lib/projects';
import { create, index, show } from '@/routes/projects';
import type { Choice, Paginated, ProjectRecord, TableColumn } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Projects', href: index() }],
    },
});

const props = defineProps<{
    projects: Paginated<ProjectRecord>;
    search: string;
    creating?: boolean;
    clients?: Choice[];
}>();

const filters = useListFilters(index.url, { search: props.search });

const page = usePage();
const canManage = computed(() =>
    page.props.auth.can.includes('manage-projects'),
);

const columns = computed<TableColumn[]>(() => [
    { key: 'name', label: 'Name', phone: 'primary' },
    { key: 'client', label: 'Client', phone: 'secondary' },
    { key: 'dates', label: 'Dates', phone: 'secondary' },
    ...(canManage.value
        ? [
              {
                  key: 'actions',
                  label: '',
                  align: 'end' as const,
                  phone: 'end' as const,
              },
          ]
        : []),
]);

const searchQuery = computed(() =>
    props.search === '' ? {} : { search: props.search },
);

const { open: sheetOpen, setOpen: setSheetOpen } = useOverlayRoute(
    () => props.creating === true,
    () => index({ query: searchQuery.value }).url,
);

const deleting = ref<ProjectRecord | null>(null);
const deleteOpen = ref(false);

function askToDelete(project: ProjectRecord): void {
    deleting.value = project;
    deleteOpen.value = true;
}
</script>

<template>
    <Head title="Projects" />

    <PageContainer width="full">
        <PageHeader
            title="Projects"
            description="The work your Employees can be assigned to."
        >
            <template v-if="canManage" #actions>
                <Button as-child>
                    <Link
                        :href="create({ query: searchQuery })"
                        preserve-scroll
                        preserve-state
                        ><Plus />New Project</Link
                    >
                </Button>
            </template>
        </PageHeader>

        <FilterBar>
            <FilterSearch v-model="filters.search" />
        </FilterBar>

        <DataTable :columns="columns" :rows="projects.data">
            <template #cell-name="{ row }">
                <span class="inline-flex flex-wrap items-center gap-2">
                    <Link
                        :href="show(row.id)"
                        class="font-medium underline-offset-4 hover:underline"
                        >{{ row.name }}</Link
                    >
                    <Badge v-if="row.is_internal" variant="secondary"
                        >Internal Project</Badge
                    >
                </span>
            </template>
            <template #cell-client="{ row }">
                <ValueOr :value="row.client?.name ?? null" />
            </template>
            <template #cell-dates="{ row }">
                <span v-if="row.start_date" class="tabular-nums">{{
                    formatPeriod(row.start_date, row.end_date)
                }}</span>
                <ValueOr v-else :value="null" />
            </template>
            <template #cell-actions="{ row }">
                <RowActions v-if="!row.is_internal" :label="row.name">
                    <template #menu>
                        <DropdownMenuItem
                            variant="destructive"
                            @select="askToDelete(row)"
                            >Delete…</DropdownMenuItem
                        >
                    </template>
                </RowActions>
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
                        <Button variant="secondary" as-child>
                            <Link
                                :href="create({ query: searchQuery })"
                                preserve-scroll
                                preserve-state
                                >New Project</Link
                            >
                        </Button>
                    </template>
                </EmptyState>
            </template>
        </DataTable>

        <Pagination :page="projects" />

        <ConfirmDeleteDialog
            v-if="deleting"
            v-model:open="deleteOpen"
            without-trigger
            :form="ProjectController.destroy.form(deleting.id)"
            :title="`Delete ${deleting.name}`"
            :description="deleteProjectDescription"
            confirm-label="Delete Project"
        />

        <ProjectSheet
            v-if="canManage"
            :open="sheetOpen"
            :clients="clients ?? []"
            @update:open="setSheetOpen"
        />
    </PageContainer>
</template>

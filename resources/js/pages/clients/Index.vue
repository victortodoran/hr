<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Briefcase, Plus } from '@lucide/vue';
import { ref } from 'vue';
import ClientController from '@/actions/App/Http/Controllers/Projects/ClientController';
import DataTable from '@/components/DataTable.vue';
import EmptyState from '@/components/EmptyState.vue';
import FilterBar from '@/components/FilterBar.vue';
import FilterSearch from '@/components/FilterSearch.vue';
import PageContainer from '@/components/PageContainer.vue';
import PageHeader from '@/components/PageHeader.vue';
import ClientDialogs from '@/components/projects/ClientDialogs.vue';
import Pagination from '@/components/Pagination.vue';
import NameDialog from '@/components/NameDialog.vue';
import RowActions from '@/components/RowActions.vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenuItem,
    DropdownMenuSeparator,
} from '@/components/ui/dropdown-menu';
import { useListFilters } from '@/composables/useListFilters';
import { projectCount } from '@/lib/projects';
import { index, show } from '@/routes/clients';
import type { ClientRecord, Paginated, TableColumn } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Clients', href: index() }],
    },
});

/**
 * The Clients the company does Projects for (rule L11). Add Client, the one
 * primary, opens a one-field dialog (L14, K7); each row's ⋯ menu renames
 * it in the same dialog or deletes it, confirming first and explaining
 * when its Projects keep it (K2, X3, X6).
 */
const props = defineProps<{
    clients: Paginated<ClientRecord>;
    search: string;
}>();

const filters = useListFilters(index.url, { search: props.search });

const columns: TableColumn[] = [
    { key: 'name', label: 'Name', phone: 'primary' },
    {
        key: 'projects_count',
        label: 'Projects',
        align: 'end',
        phone: 'secondary',
    },
    { key: 'actions', label: '', align: 'end', phone: 'end' },
];

const addOpen = ref(false);

/** The Client the Rename or Delete dialog is about. */
const chosen = ref<ClientRecord | null>(null);
const renameOpen = ref(false);
const deleteOpen = ref(false);

function askToRename(client: ClientRecord): void {
    chosen.value = client;
    renameOpen.value = true;
}

function askToDelete(client: ClientRecord): void {
    chosen.value = client;
    deleteOpen.value = true;
}
</script>

<template>
    <Head title="Clients" />

    <PageContainer width="full">
        <PageHeader
            title="Clients"
            description="The outside organisations your company does Projects for."
        >
            <template #actions>
                <Button @click="addOpen = true"><Plus />Add Client…</Button>
            </template>
        </PageHeader>

        <FilterBar>
            <FilterSearch v-model="filters.search" />
        </FilterBar>

        <DataTable :columns="columns" :rows="clients.data">
            <template #cell-name="{ row }">
                <Link
                    :href="show(row.id)"
                    class="font-medium underline-offset-4 hover:underline"
                    >{{ row.name }}</Link
                >
            </template>
            <template #cell-projects_count="{ row }">
                <span class="tabular-nums">{{
                    projectCount(row.projects_count)
                }}</span>
            </template>
            <template #cell-actions="{ row }">
                <RowActions :label="row.name">
                    <template #menu>
                        <DropdownMenuItem @select="askToRename(row)"
                            >Rename…</DropdownMenuItem
                        >
                        <DropdownMenuSeparator />
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
                    :icon="Briefcase"
                    :message="
                        search
                            ? `Clients are the outside organisations the company does Projects for. None match “${search}”.`
                            : 'Clients are the outside organisations the company does Projects for. You only need them for work done for outside organisations.'
                    "
                >
                    <template #action>
                        <Button variant="secondary" @click="addOpen = true"
                            >Add Client…</Button
                        >
                    </template>
                </EmptyState>
            </template>
        </DataTable>

        <Pagination :page="clients" />

        <NameDialog
            v-model:open="addOpen"
            without-trigger
            :form="ClientController.store.form()"
            name=""
            field-id="client-name"
            title="Add Client"
            submit-label="Add Client"
        />

        <ClientDialogs
            v-if="chosen"
            v-model:rename-open="renameOpen"
            v-model:delete-open="deleteOpen"
            :client="chosen"
        />
    </PageContainer>
</template>

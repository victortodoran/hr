<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { Briefcase } from '@lucide/vue';
import ClientController from '@/actions/App/Http/Controllers/Projects/ClientController';
import ConfirmDeleteDialog from '@/components/ConfirmDeleteDialog.vue';
import DataTable from '@/components/DataTable.vue';
import EmptyState from '@/components/EmptyState.vue';
import FilterBar from '@/components/FilterBar.vue';
import FilterSearch from '@/components/FilterSearch.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import PageContainer from '@/components/PageContainer.vue';
import Pagination from '@/components/Pagination.vue';
import RenameDialog from '@/components/RenameDialog.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useListFilters } from '@/composables/useListFilters';
import { index, show } from '@/routes/clients';
import type { ClientRecord, Paginated, TableColumn } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Clients', href: index() }],
    },
});

const props = defineProps<{
    clients: Paginated<ClientRecord>;
    search: string;
}>();

const filters = useListFilters(index.url, { search: props.search });

const columns: TableColumn[] = [
    { key: 'name', label: 'Name' },
    { key: 'projects_count', label: 'Projects', minor: true },
    { key: 'actions', label: 'Actions', align: 'end' },
];
</script>

<template>
    <Head title="Clients" />

    <PageContainer width="wide">
        <Heading
            title="Clients"
            description="The outside organisations the company does Projects for"
        />

        <Form
            v-bind="ClientController.store.form()"
            reset-on-success
            :options="{ preserveScroll: true }"
            class="flex max-w-xl items-end gap-2"
            v-slot="{ errors, processing }"
        >
            <div class="grid flex-1 gap-2">
                <Label for="new-client">New Client</Label>
                <Input
                    id="new-client"
                    name="name"
                    placeholder="e.g. Globex"
                    required
                />
                <InputError :message="errors.name" />
            </div>
            <SubmitButton :processing="processing">Add</SubmitButton>
        </Form>

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
                {{ row.projects_count }}
                {{ row.projects_count === 1 ? 'Project' : 'Projects' }}
            </template>
            <template #head-actions>
                <span class="sr-only">Actions</span>
            </template>
            <template #cell-actions="{ row }">
                <div class="flex justify-end gap-2">
                    <RenameDialog
                        :form="ClientController.update.form(row)"
                        :name="row.name"
                        :field-id="`client-name-${row.id}`"
                    />
                    <ConfirmDeleteDialog
                        :form="ClientController.destroy.form(row)"
                        :title="`Delete ${row.name}?`"
                        description="Only a Client with no Projects can be deleted."
                    />
                </div>
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
                        <Button variant="outline" as-child>
                            <label for="new-client">Add Client</label>
                        </Button>
                    </template>
                </EmptyState>
            </template>
        </DataTable>

        <Pagination :page="clients" />
    </PageContainer>
</template>

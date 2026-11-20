<script setup lang="ts">
import ClientController from '@/actions/App/Http/Controllers/Projects/ClientController';
import ConfirmDeleteDialog from '@/components/ConfirmDeleteDialog.vue';
import NameDialog from '@/components/NameDialog.vue';
import { projectCount } from '@/lib/projects';
import type { ClientRecord } from '@/types';

defineProps<{
    client: ClientRecord;
}>();

const renameOpen = defineModel<boolean>('renameOpen', { default: false });
const deleteOpen = defineModel<boolean>('deleteOpen', { default: false });

function deleteDescription(client: ClientRecord): string {
    return client.projects_count === 0
        ? "This can't be undone."
        : `${client.name} still has ${projectCount(client.projects_count)}. Move or delete them before deleting the Client.`;
}
</script>

<template>
    <div class="contents">
        <NameDialog
            v-model:open="renameOpen"
            without-trigger
            :form="ClientController.update.form(client)"
            :name="client.name"
            field-id="client-name"
        />
        <ConfirmDeleteDialog
            v-model:open="deleteOpen"
            without-trigger
            :form="ClientController.destroy.form(client)"
            :title="`Delete ${client.name}`"
            :description="deleteDescription(client)"
            confirm-label="Delete Client"
        />
    </div>
</template>

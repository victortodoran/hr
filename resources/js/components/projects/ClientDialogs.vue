<script setup lang="ts">
import ClientController from '@/actions/App/Http/Controllers/Projects/ClientController';
import ConfirmDeleteDialog from '@/components/ConfirmDeleteDialog.vue';
import NameDialog from '@/components/NameDialog.vue';
import { projectCount } from '@/lib/projects';
import type { ClientRecord } from '@/types';

/**
 * A Client's Rename and Delete dialogs, shared by the Clients' ⋯ menus and
 * the Client's page (K7, C7). Delete confirms first, and explains instead
 * when the Client's Projects keep it (X3).
 */
defineProps<{
    client: ClientRecord;
}>();

const renameOpen = defineModel<boolean>('renameOpen', { default: false });
const deleteOpen = defineModel<boolean>('deleteOpen', { default: false });

/** What deleting the Client asks about, or why it can't be deleted (X3). */
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

<script setup lang="ts">
import { Trash2 } from '@lucide/vue';
import WorkEntryController from '@/actions/App/Http/Controllers/WorkEntries/WorkEntryController';
import ConfirmDeleteDialog from '@/components/ConfirmDeleteDialog.vue';
import { Button } from '@/components/ui/button';
import type { WorkEntryRecord } from '@/types';

/**
 * Deletes a Work Entry from its icon button, through the app's one
 * confirm-delete dialog; its three places share this trigger and wording.
 */
defineProps<{
    entry: WorkEntryRecord;
    /** The URL query asking for the Period shown, so deleting returns to it. */
    periodQuery: Record<string, string>;
}>();
</script>

<template>
    <ConfirmDeleteDialog
        :form="
            WorkEntryController.destroy.form(entry.id, { query: periodQuery })
        "
        title="Delete this Work Entry?"
        :description="`${entry.hours} h on ${entry.project} · ${entry.description}. This can't be undone.`"
    >
        <template #trigger>
            <Button
                type="button"
                variant="ghost"
                size="icon-sm"
                class="text-muted-foreground hover:text-destructive"
                aria-label="Delete Work Entry"
                ><Trash2
            /></Button>
        </template>
    </ConfirmDeleteDialog>
</template>

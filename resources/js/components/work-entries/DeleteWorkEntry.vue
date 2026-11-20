<script setup lang="ts">
import { Trash2 } from '@lucide/vue';
import WorkEntryController from '@/actions/App/Http/Controllers/WorkEntries/WorkEntryController';
import ConfirmDeleteDialog from '@/components/ConfirmDeleteDialog.vue';
import { Button } from '@/components/ui/button';
import type { WorkEntryRecord } from '@/types';

/**
 * Deletes a Work Entry through the app's one confirm-delete dialog, its
 * title a statement (rule C7); its places share this wording. It opens
 * from its own icon button, or, with `without-trigger`, through
 * `v-model:open` from a ⋯ menu's Delete item.
 */
defineProps<{
    entry: WorkEntryRecord;
    /** The URL query asking for the Period shown, so deleting returns to it. */
    periodQuery: Record<string, string>;
    /** Leaves out the icon button, for a dialog opened from a menu. */
    withoutTrigger?: boolean;
}>();

const open = defineModel<boolean>('open', { default: false });
</script>

<template>
    <ConfirmDeleteDialog
        v-model:open="open"
        :form="
            WorkEntryController.destroy.form(entry.id, { query: periodQuery })
        "
        title="Delete Work Entry"
        :description="`${entry.hours} h on ${entry.project} · ${entry.description}. This can't be undone.`"
        confirm-label="Delete Work Entry"
        :without-trigger="withoutTrigger"
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

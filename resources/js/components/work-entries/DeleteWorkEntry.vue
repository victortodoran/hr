<script setup lang="ts">
import { Trash2 } from '@lucide/vue';
import WorkEntryController from '@/actions/App/Http/Controllers/WorkEntries/WorkEntryController';
import ConfirmDeleteDialog from '@/components/ConfirmDeleteDialog.vue';
import { Button } from '@/components/ui/button';
import type { WorkEntryRecord } from '@/types';

defineProps<{
    entry: WorkEntryRecord;
    periodQuery: Record<string, string>;
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

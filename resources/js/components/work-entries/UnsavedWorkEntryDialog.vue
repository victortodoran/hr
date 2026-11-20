<script setup lang="ts">
import UnsavedChangesDialog from '@/components/UnsavedChangesDialog.vue';

defineProps<{
    open: boolean;
}>();

const emit = defineEmits<{
    stay: [];
    leave: [];
    /** The dialog closed and is about to put focus back where it came from; preventing it keeps focus where the page puts it. */
    closeAutoFocus: [event: Event];
}>();
</script>

<template>
    <UnsavedChangesDialog
        :open="open"
        title="Leave without saving?"
        description="You have an unsaved Work Entry. Leave anyway?"
        keep-label="Stay"
        discard-label="Leave"
        @keep="emit('stay')"
        @discard="emit('leave')"
        @close-auto-focus="emit('closeAutoFocus', $event)"
    />
</template>

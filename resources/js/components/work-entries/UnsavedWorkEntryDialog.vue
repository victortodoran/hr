<script setup lang="ts">
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

/**
 * Asks before typing in a Work Entry that isn't saved yet is given up.
 */
defineProps<{
    open: boolean;
}>();

const emit = defineEmits<{
    /** Keep the unsaved Work Entry. */
    stay: [];
    /** Give it up and go on. */
    leave: [];
    /** The dialog closed and is about to put focus back where it came from; preventing it keeps focus where the page puts it. */
    closeAutoFocus: [event: Event];
}>();
</script>

<template>
    <Dialog :open="open" @update:open="(isOpen) => !isOpen && emit('stay')">
        <DialogContent @close-auto-focus="emit('closeAutoFocus', $event)">
            <DialogHeader>
                <DialogTitle>Leave without saving?</DialogTitle>
                <DialogDescription>
                    You have an unsaved Work Entry. Leave anyway?
                </DialogDescription>
            </DialogHeader>
            <DialogFooter class="gap-2">
                <Button variant="secondary" @click="emit('stay')">Stay</Button>
                <Button variant="destructive" @click="emit('leave')"
                    >Leave</Button
                >
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>

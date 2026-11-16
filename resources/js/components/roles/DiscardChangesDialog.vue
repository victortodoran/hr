<script setup lang="ts">
import {
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogOverlay,
    AlertDialogPortal,
    AlertDialogRoot,
    AlertDialogTitle,
} from 'reka-ui';
import { Button, buttonVariants } from '@/components/ui/button';

/**
 * Asks whether to leave and lose unsaved changes. "Keep editing" is focused
 * first, so the safe choice is the easy one; it, Escape and closing the
 * dialog all keep editing. "Discard" only discards, leaving the dialog to
 * close as the page leaves.
 */
defineProps<{
    open: boolean;
    title: string;
    description: string;
}>();

const emit = defineEmits<{
    keep: [];
    discard: [];
}>();
</script>

<template>
    <AlertDialogRoot
        :open="open"
        @update:open="(isOpen: boolean) => !isOpen && emit('keep')"
    >
        <AlertDialogPortal>
            <AlertDialogOverlay class="fixed inset-0 z-50 bg-black/50" />
            <AlertDialogContent
                class="fixed top-[50%] left-[50%] z-50 grid w-full max-w-[calc(100%-2rem)] translate-x-[-50%] translate-y-[-50%] gap-4 rounded-lg border bg-background p-6 shadow-lg sm:max-w-md"
            >
                <AlertDialogTitle class="text-lg font-semibold">{{
                    title
                }}</AlertDialogTitle>
                <AlertDialogDescription class="text-sm text-muted-foreground">{{
                    description
                }}</AlertDialogDescription>
                <div class="flex flex-wrap justify-end gap-2">
                    <AlertDialogCancel
                        :class="buttonVariants({ variant: 'secondary' })"
                        class="min-h-11"
                        >Keep editing</AlertDialogCancel
                    >
                    <Button
                        variant="destructive"
                        class="min-h-11"
                        @click="emit('discard')"
                        >Discard</Button
                    >
                </div>
            </AlertDialogContent>
        </AlertDialogPortal>
    </AlertDialogRoot>
</template>

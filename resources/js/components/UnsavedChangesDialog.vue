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
 * Asks whether to give up unsaved changes, the one such question across the
 * app. The keep button ("Keep editing" unless told otherwise) is focused
 * first, so the safe choice is the easy one; it and Escape both keep. The
 * discard button only discards, leaving the page to close the dialog as it
 * leaves or moves on.
 */
withDefaults(
    defineProps<{
        open: boolean;
        title: string;
        description: string;
        keepLabel?: string;
        discardLabel?: string;
    }>(),
    { keepLabel: 'Keep editing', discardLabel: 'Discard' },
);

const emit = defineEmits<{
    keep: [];
    discard: [];
    /** The dialog closed and is about to put focus back where it came from; preventing it keeps focus where the page puts it. */
    closeAutoFocus: [event: Event];
}>();
</script>

<template>
    <AlertDialogRoot
        :open="open"
        @update:open="(isOpen: boolean) => !isOpen && emit('keep')"
    >
        <AlertDialogPortal>
            <AlertDialogOverlay class="fixed inset-0 z-50 bg-overlay" />
            <AlertDialogContent
                @close-auto-focus="emit('closeAutoFocus', $event)"
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
                        >{{ keepLabel }}</AlertDialogCancel
                    >
                    <Button
                        variant="destructive"
                        class="min-h-11"
                        @click="emit('discard')"
                        >{{ discardLabel }}</Button
                    >
                </div>
            </AlertDialogContent>
        </AlertDialogPortal>
    </AlertDialogRoot>
</template>

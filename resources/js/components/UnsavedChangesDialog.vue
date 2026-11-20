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

/** The keep button is focused first, so the safe choice is the easy one. */
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
                class="fixed z-50 grid w-full gap-4 border bg-background p-6 shadow-overlay max-sm:inset-x-0 max-sm:bottom-0 max-sm:rounded-t-xl max-sm:pb-[max(1.5rem,env(safe-area-inset-bottom))] sm:top-[50%] sm:left-[50%] sm:max-w-md sm:translate-x-[-50%] sm:translate-y-[-50%] sm:rounded-xl"
            >
                <AlertDialogTitle class="text-section">{{
                    title
                }}</AlertDialogTitle>
                <AlertDialogDescription
                    class="text-body text-muted-foreground"
                    >{{ description }}</AlertDialogDescription
                >
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

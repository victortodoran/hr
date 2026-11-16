<script setup lang="ts">
import SubmitButton from '@/components/SubmitButton.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';

/**
 * Asks before locking the selected Travel Expenses for good. The page sends
 * the lock and closes the dialog once it is done; while it is on its way
 * the dialog stays open and can't send it again.
 */
defineProps<{
    /** How many Travel Expenses are selected. */
    count: number;
    /** Whether the lock is on its way. */
    processing: boolean;
}>();

const open = defineModel<boolean>('open', { required: true });

const emit = defineEmits<{
    lock: [];
}>();

/** Opens or closes the dialog, but keeps it open while locking. */
function setOpen(isOpen: boolean, processing: boolean): void {
    if (!processing) {
        open.value = isOpen;
    }
}
</script>

<template>
    <Dialog :open="open" @update:open="setOpen($event, processing)">
        <DialogTrigger as-child>
            <Button variant="outline" :disabled="count === 0">Lock</Button>
        </DialogTrigger>
        <DialogContent>
            <DialogHeader>
                <DialogTitle
                    >Lock {{ count }}
                    {{
                        count === 1 ? 'Travel Expense' : 'Travel Expenses'
                    }}?</DialogTitle
                >
                <DialogDescription>
                    Only Approved ones are locked; any other is skipped. Locked
                    Travel Expenses can never be changed, deleted or reopened.
                    This can't be undone.
                </DialogDescription>
            </DialogHeader>
            <DialogFooter class="gap-2">
                <DialogClose as-child>
                    <Button variant="secondary">Cancel</Button>
                </DialogClose>
                <SubmitButton
                    variant="destructive"
                    :processing="processing"
                    type="button"
                    @click="emit('lock')"
                    >Lock for good</SubmitButton
                >
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>

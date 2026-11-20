<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
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
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import type { RouteDefinition } from '@/wayfinder';

/**
 * Declines one or more records of one kind, asking for the one reason the
 * Employee will read on each (rule X5): the selected Work Entries or
 * Travel Expenses on the Approvals page, one of them from its row or
 * RecordSheet, or one Time Off or Travel Request by its URL. It says
 * exactly what is declined, and its confirm button repeats the verb in
 * `primary`, not red, as declining isn't deleting (C7, F5). On a phone it
 * opens as a bottom sheet.
 */
const props = defineProps<{
    /** The selected rows; left out when the action declines one thing by its URL. */
    ids?: number[];
    /** Where the decline is sent. */
    action: RouteDefinition<'post'>;
    /** What is declined, such as "Work Entry" and "Work Entries". */
    noun: { one: string; many: string };
    /** Exactly what is declined, such as "Cara Lane · Tue, Nov 10 · 5 h on Relaunch". */
    summary?: string;
    /** What declining means for the Employee, shown under the reason. */
    description: string;
    placeholder: string;
    /** The trigger button's size, smaller in a table row. */
    size?: 'default' | 'sm';
    /** The trigger button's accessible name, telling one row's from another's. */
    accessibleName?: string;
}>();

defineSlots<{
    /** Replaces the "Decline…" button, such as with a row's icon button. */
    trigger?: () => unknown;
}>();

const emit = defineEmits<{
    declined: [];
}>();

const open = ref(false);

const form = useForm({ ids: [] as number[], decline_reason: '' });

form.transform((data) =>
    props.ids === undefined ? { decline_reason: data.decline_reason } : data,
);

/** "2 Work Entries", or the noun alone for one record. */
function counted(): string {
    const count = props.ids?.length ?? 1;

    return count === 1 ? props.noun.one : `${count} ${props.noun.many}`;
}

function opened(isOpen: boolean): void {
    if (isOpen) {
        form.reset();
        form.clearErrors();
    }
}

function decline(): void {
    form.ids = props.ids ?? [];
    form.submit(props.action, {
        preserveScroll: true,
        onSuccess: () => {
            open.value = false;
            emit('declined');
        },
    });
}
</script>

<template>
    <Dialog v-model:open="open" @update:open="opened">
        <DialogTrigger as-child>
            <slot name="trigger">
                <Button
                    variant="secondary"
                    :size="size"
                    :aria-label="accessibleName"
                    :disabled="ids !== undefined && ids.length === 0"
                    >Decline…</Button
                >
            </slot>
        </DialogTrigger>
        <DialogContent>
            <form class="flex flex-col gap-4" @submit.prevent="decline">
                <DialogHeader>
                    <DialogTitle>Decline {{ counted() }}</DialogTitle>
                    <DialogDescription v-if="summary">{{
                        summary
                    }}</DialogDescription>
                </DialogHeader>
                <div class="grid gap-2">
                    <Label for="decline-reason">Reason</Label>
                    <Textarea
                        id="decline-reason"
                        v-model="form.decline_reason"
                        :placeholder="placeholder"
                        rows="3"
                        required
                    />
                    <p class="text-meta text-muted-foreground">
                        {{ description }}
                    </p>
                    <InputError :message="form.errors.decline_reason" />
                    <InputError :message="form.errors.ids" />
                </div>
                <DialogFooter class="gap-2">
                    <DialogClose as-child>
                        <Button type="button" variant="secondary"
                            >Cancel</Button
                        >
                    </DialogClose>
                    <SubmitButton :processing="form.processing"
                        >Decline {{ counted() }}</SubmitButton
                    >
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>

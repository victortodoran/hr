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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { RouteDefinition } from '@/wayfinder';

/**
 * Declines the selected Work Entries or Travel Expenses on the Approvals
 * page, asking for the one reason the Employee will see on each.
 */
const props = defineProps<{
    /** The selected rows. */
    ids: number[];
    /** Where the decline is sent. */
    action: RouteDefinition<'post'>;
    /** What is declined, such as "Work Entry" and "Work Entries". */
    noun: { one: string; many: string };
    /** What declining means for the Employee. */
    description: string;
    placeholder: string;
}>();

const emit = defineEmits<{
    declined: [];
}>();

const open = ref(false);

const form = useForm({ ids: [] as number[], decline_reason: '' });

function opened(isOpen: boolean): void {
    if (isOpen) {
        form.reset();
        form.clearErrors();
    }
}

function decline(): void {
    form.ids = props.ids;
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
            <Button variant="outline" :disabled="ids.length === 0"
                >Decline…</Button
            >
        </DialogTrigger>
        <DialogContent>
            <form class="flex flex-col gap-4" @submit.prevent="decline">
                <DialogHeader>
                    <DialogTitle
                        >Decline {{ ids.length }}
                        {{
                            ids.length === 1 ? noun.one : noun.many
                        }}?</DialogTitle
                    >
                    <DialogDescription>{{ description }}</DialogDescription>
                </DialogHeader>
                <div class="grid gap-2">
                    <Label for="decline-reason">Reason</Label>
                    <Input
                        id="decline-reason"
                        v-model="form.decline_reason"
                        :placeholder="placeholder"
                        required
                    />
                    <InputError :message="form.errors.decline_reason" />
                    <InputError :message="form.errors.ids" />
                </div>
                <DialogFooter class="gap-2">
                    <DialogClose as-child>
                        <Button type="button" variant="secondary"
                            >Cancel</Button
                        >
                    </DialogClose>
                    <SubmitButton
                        :processing="form.processing"
                        variant="destructive"
                        >Decline</SubmitButton
                    >
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>

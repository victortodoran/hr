<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import WorkEntryDeclineController from '@/actions/App/Http/Controllers/WorkEntryDeclineController';
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

/**
 * Declines the selected Work Entries, asking for the one reason the
 * Employee will see on each.
 */
const props = defineProps<{
    /** The selected Work Entries. */
    ids: number[];
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
    form.submit(WorkEntryDeclineController.store(), {
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
                        >Decline {{ ids.length }} Work
                        {{
                            ids.length === 1 ? 'Entry' : 'Entries'
                        }}?</DialogTitle
                    >
                    <DialogDescription>
                        The Employee sees the reason under each one, and can
                        change or delete it. Declined hours aren't counted.
                    </DialogDescription>
                </DialogHeader>
                <div class="grid gap-2">
                    <Label for="decline-reason">Reason</Label>
                    <Input
                        id="decline-reason"
                        v-model="form.decline_reason"
                        placeholder="Interviews go on Internal, not Acme"
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

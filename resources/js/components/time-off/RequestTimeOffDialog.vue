<script setup lang="ts">
import { useForm, useHttp } from '@inertiajs/vue3';
import { onBeforeUnmount, ref, watch } from 'vue';
import TimeOffController from '@/actions/App/Http/Controllers/TimeOffController';
import TimeOffPreviewController from '@/actions/App/Http/Controllers/TimeOffPreviewController';
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
import { formatDays } from '@/lib/time-off';
import type { TimeOffPreview } from '@/types';

defineProps<{
    today: string;
}>();

/** How long the dates and kind must stay unchanged before previewing them. */
const PREVIEW_DELAY_MS = 300;

const open = ref(false);
const preview = ref<TimeOffPreview | null>(null);

const form = useForm({
    first_date: '',
    last_date: '',
    is_paid: true,
});

/** The preview's query; Laravel reads Paid or Unpaid as 1 or 0. */
const http = useHttp<
    { first_date: string; last_date: string; is_paid: number },
    TimeOffPreview
>({
    first_date: '',
    last_date: '',
    is_paid: 1,
});

let previewTimer: ReturnType<typeof setTimeout> | undefined;

/**
 * Preview the days the Time Off would take once both dates are chosen,
 * keeping only the answer to the latest dates and kind.
 */
async function loadPreview(): Promise<void> {
    http.cancel();
    http.first_date = form.first_date;
    http.last_date = form.last_date;
    http.is_paid = form.is_paid ? 1 : 0;

    try {
        preview.value =
            (await http.get(TimeOffPreviewController.show.url())) ?? null;
    } catch {
        // A cancelled or failed preview leaves nothing to show.
        preview.value = null;
    }
}

watch(
    () => [form.first_date, form.last_date, form.is_paid],
    () => {
        clearTimeout(previewTimer);
        preview.value = null;
        http.clearErrors();

        if (form.first_date === '' || form.last_date === '') {
            http.cancel();

            return;
        }

        previewTimer = setTimeout(loadPreview, PREVIEW_DELAY_MS);
    },
);

watch(open, (isOpen) => {
    if (isOpen) {
        form.reset();
        form.clearErrors();
    }
});

onBeforeUnmount(() => clearTimeout(previewTimer));

/**
 * Paid Days as people read them, such as "1 Paid Day" or "3 Paid Days".
 */
function paidDays(days: number): string {
    return days === 1 ? '1 Paid Day' : `${days} Paid Days`;
}

function request(): void {
    form.submit(TimeOffController.store(), {
        preserveScroll: true,
        onSuccess: () => {
            open.value = false;
        },
    });
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogTrigger as-child>
            <Button>Request Time Off</Button>
        </DialogTrigger>
        <DialogContent>
            <DialogHeader>
                <DialogTitle>Request Time Off</DialogTitle>
                <DialogDescription>
                    Only weekdays count. Paid Time Off uses your Paid Days for
                    the year each day falls in.
                </DialogDescription>
            </DialogHeader>

            <form class="space-y-4" @submit.prevent="request">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="time-off-first-date">First date</Label>
                        <Input
                            id="time-off-first-date"
                            v-model="form.first_date"
                            type="date"
                            :min="today"
                            required
                        />
                        <InputError
                            :message="
                                form.errors.first_date ?? http.errors.first_date
                            "
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label for="time-off-last-date">Last date</Label>
                        <Input
                            id="time-off-last-date"
                            v-model="form.last_date"
                            type="date"
                            :min="form.first_date || today"
                            required
                        />
                        <InputError
                            :message="
                                form.errors.last_date ?? http.errors.last_date
                            "
                        />
                    </div>
                </div>
                <fieldset class="grid gap-2">
                    <legend class="mb-2 text-sm font-medium">Kind</legend>
                    <div class="flex gap-6 text-sm">
                        <label class="flex items-center gap-2">
                            <input
                                v-model="form.is_paid"
                                type="radio"
                                :value="true"
                            />
                            Paid
                        </label>
                        <label class="flex items-center gap-2">
                            <input
                                v-model="form.is_paid"
                                type="radio"
                                :value="false"
                            />
                            Unpaid
                        </label>
                    </div>
                    <InputError
                        :message="form.errors.is_paid ?? http.errors.is_paid"
                    />
                </fieldset>

                <div
                    v-if="preview"
                    class="space-y-1 rounded-md bg-muted px-3 py-2 text-sm"
                    aria-live="polite"
                >
                    <p class="font-medium">
                        {{ formatDays(preview.weekdays) }}
                        {{ form.is_paid ? 'Paid' : 'Unpaid' }}
                    </p>
                    <p
                        v-for="year in preview.years"
                        :key="year.year"
                        :class="
                            year.left_after < 0
                                ? 'text-destructive'
                                : 'text-muted-foreground'
                        "
                    >
                        {{ year.year }}: uses {{ paidDays(year.uses) }},
                        <template v-if="year.left_after < 0"
                            >{{ paidDays(-year.left_after) }} more than you have
                            left. Request the rest as Unpaid Time Off.</template
                        >
                        <template v-else
                            >leaving {{ paidDays(year.left_after) }}.</template
                        >
                    </p>
                </div>
                <p
                    v-else-if="http.processing"
                    class="text-sm text-muted-foreground"
                >
                    Counting the days…
                </p>

                <DialogFooter class="gap-2">
                    <DialogClose as-child>
                        <Button variant="secondary" type="button"
                            >Cancel</Button
                        >
                    </DialogClose>
                    <SubmitButton :processing="form.processing"
                        >Request</SubmitButton
                    >
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>

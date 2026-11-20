<script setup lang="ts">
import { useForm, useHttp } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import TimeOffController from '@/actions/App/Http/Controllers/TimeOff/TimeOffController';
import TimeOffPreviewController from '@/actions/App/Http/Controllers/TimeOff/TimeOffPreviewController';
import Callout from '@/components/Callout.vue';
import DateRangePicker from '@/components/DateRangePicker.vue';
import FormSheet from '@/components/FormSheet.vue';
import InputError from '@/components/InputError.vue';
import SegmentedControl from '@/components/SegmentedControl.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import type { PaidDaysLeftInYear, TimeOffPreview } from '@/types';

/**
 * Requesting Time Off in a sheet over the Time Off page (rule L14): its
 * dates, weekdays only, and its kind, Paid or Unpaid, with the Paid Days
 * left in each year the dates fall in once it's requested, counted live.
 * Once requested, the page shows it as Pending and the sheet closes.
 */
const props = defineProps<{
    /** The earliest first date, as YYYY-MM-DD. */
    today: string;
    /** This year's Paid Days left and, from October, next year's. */
    paidDaysLeft: PaidDaysLeftInYear[];
}>();

const open = defineModel<boolean>('open', { required: true });

/** How long the dates and kind must stay unchanged before previewing them. */
const PREVIEW_DELAY_MS = 300;

const FORM_ID = 'request-time-off';

const kinds: { value: 'paid' | 'unpaid'; label: string }[] = [
    { value: 'paid', label: 'Paid' },
    { value: 'unpaid', label: 'Unpaid' },
];

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

/** Starts each request blank. */
watch(open, (isOpen) => {
    if (isOpen) {
        form.reset();
        form.clearErrors();
    }
});

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

onBeforeUnmount(() => {
    clearTimeout(previewTimer);
    http.cancel();
});

/**
 * Paid Days as people read them, such as "1 Paid Day" or "3 Paid Days".
 */
/**
 * Whether the Paid Time Off asked for uses more Paid Days than are left in
 * some year, so the preview becomes an attention Callout (rule K12).
 */
const isOverPaidDays = computed(
    () =>
        form.is_paid &&
        preview.value !== null &&
        preview.value.years.some((year) => year.left_after < 0),
);

function paidDays(days: number): string {
    return days === 1 ? '1 Paid Day' : `${days} Paid Days`;
}

function request(): void {
    form.submit(TimeOffController.store(), {
        preserveScroll: true,
        preserveState: true,
    });
}
</script>

<template>
    <FormSheet
        v-model:open="open"
        title="Request Time Off"
        description="Only weekdays count. Paid Time Off uses your Paid Days for the year each day falls in."
    >
        <form
            :id="FORM_ID"
            class="flex flex-col gap-5"
            @submit.prevent="request"
        >
            <div class="grid gap-2">
                <Label for="time-off-dates">Dates</Label>
                <DateRangePicker
                    id="time-off-dates"
                    v-model:start="form.first_date"
                    v-model:end="form.last_date"
                    :min="props.today"
                    weekdays-only
                    required
                    :invalid="
                        Boolean(
                            form.errors.first_date ??
                            form.errors.last_date ??
                            http.errors.first_date ??
                            http.errors.last_date,
                        )
                    "
                />
                <InputError
                    :message="form.errors.first_date ?? http.errors.first_date"
                />
                <InputError
                    :message="form.errors.last_date ?? http.errors.last_date"
                />
            </div>

            <div class="grid justify-items-start gap-2">
                <span class="text-meta font-medium" aria-hidden="true"
                    >Kind</span
                >
                <SegmentedControl
                    :model-value="form.is_paid ? 'paid' : 'unpaid'"
                    :options="kinds"
                    label="Kind"
                    @update:model-value="form.is_paid = $event === 'paid'"
                />
                <InputError
                    :message="form.errors.is_paid ?? http.errors.is_paid"
                />
            </div>

            <Callout
                v-if="isOverPaidDays && preview"
                tone="attention"
                aria-live="polite"
                data-test="paid-days-preview"
            >
                <p v-for="year in preview.years" :key="year.year">
                    {{ year.year }}: uses {{ paidDays(year.uses) }},
                    <template v-if="year.left_after < 0"
                        >{{ paidDays(-year.left_after) }} more than you have
                        left. Request the rest as Unpaid Time Off.</template
                    >
                    <template v-else
                        >leaving {{ paidDays(year.left_after) }}.</template
                    >
                </p>
            </Callout>
            <div
                v-else
                class="flex flex-col gap-1 rounded-md bg-muted px-3 py-2 text-meta"
                aria-live="polite"
                data-test="paid-days-preview"
            >
                <template v-if="!form.is_paid">
                    <p class="text-muted-foreground">
                        Unpaid Time Off uses no Paid Days.
                    </p>
                </template>
                <template v-else-if="preview">
                    <p
                        v-for="year in preview.years"
                        :key="year.year"
                        class="text-muted-foreground"
                    >
                        {{ year.year }}: uses {{ paidDays(year.uses) }}, leaving
                        {{ paidDays(year.left_after) }}.
                    </p>
                </template>
                <p v-else-if="http.processing" class="text-muted-foreground">
                    Counting the days…
                </p>
                <template v-else>
                    <p
                        v-for="inYear in paidDaysLeft"
                        :key="inYear.year"
                        class="text-muted-foreground"
                    >
                        {{ inYear.year }}: {{ paidDays(inYear.days) }} left.
                    </p>
                </template>
            </div>
        </form>

        <template #footer>
            <Button type="button" variant="ghost" @click="open = false"
                >Cancel</Button
            >
            <SubmitButton :form="FORM_ID" :processing="form.processing"
                >Request Time Off</SubmitButton
            >
        </template>
    </FormSheet>
</template>

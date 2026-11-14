<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { Copy } from '@lucide/vue';
import WorkEntryCopyController from '@/actions/App/Http/Controllers/WorkEntryCopyController';
import InputError from '@/components/InputError.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import { formatWeekdayAndDay } from '@/lib/dates';
import type { Choice } from '@/types';

/**
 * Copies the Work Entries of the latest earlier day with any onto a day,
 * such as "Copy entries from Thu 12", saying why when it's refused.
 */
const props = defineProps<{
    employee: Choice;
    /** The URL query asking for the Period shown, so copying returns to it. */
    periodQuery: Record<string, string>;
    /** The day to copy onto. */
    date: string;
    /** The day to copy from. */
    copyFrom: string;
}>();

const form = useForm({ date: '' });

function copy(): void {
    form.date = props.date;
    form.submit(
        WorkEntryCopyController.store(props.employee.id, {
            query: props.periodQuery,
        }),
        { preserveScroll: true },
    );
}

/**
 * The day copied from, such as "Thu 12", naming its month when it falls in
 * another one than the day copied onto: "Fri 30 Oct".
 */
function formatSource(): string {
    if (props.copyFrom.slice(0, 7) === props.date.slice(0, 7)) {
        return formatWeekdayAndDay(props.copyFrom);
    }

    const month = new Date(`${props.copyFrom}T00:00:00`).toLocaleDateString(
        undefined,
        { month: 'short' },
    );

    return `${formatWeekdayAndDay(props.copyFrom)} ${month}`;
}
</script>

<template>
    <form class="flex flex-col items-start gap-1" @submit.prevent="copy">
        <SubmitButton
            :processing="form.processing"
            variant="ghost"
            size="sm"
            class="h-7 text-foreground"
            ><Copy />Copy entries from {{ formatSource() }}</SubmitButton
        >
        <InputError :message="form.errors.date" />
    </form>
</template>

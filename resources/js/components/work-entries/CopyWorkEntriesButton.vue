<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { Copy } from '@lucide/vue';
import WorkEntryCopyController from '@/actions/App/Http/Controllers/WorkEntries/WorkEntryCopyController';
import InputError from '@/components/InputError.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import { formatWeekdayAndDay } from '@/lib/dates';
import type { Choice } from '@/types';

/** A plain button, so it can sit inside the day panel's add form. */
const props = defineProps<{
    employee: Choice;
    periodQuery: Record<string, string>;
    date: string;
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

function label(): string {
    const from = new Date(`${props.copyFrom}T00:00:00`);
    const daysBefore =
        (new Date(`${props.date}T00:00:00`).getTime() - from.getTime()) /
        86_400_000;

    if (daysBefore < 7) {
        return `Copy ${from.toLocaleDateString(undefined, { weekday: 'long' })}'s Work Entries`;
    }

    if (props.copyFrom.slice(0, 7) === props.date.slice(0, 7)) {
        return `Copy Work Entries from ${formatWeekdayAndDay(props.copyFrom)}`;
    }

    const month = from.toLocaleDateString(undefined, { month: 'short' });

    return `Copy Work Entries from ${formatWeekdayAndDay(props.copyFrom)} ${month}`;
}
</script>

<template>
    <div class="flex flex-col items-start gap-1">
        <SubmitButton
            type="button"
            :processing="form.processing"
            variant="ghost"
            size="sm"
            class="text-foreground"
            @click="copy"
            ><Copy />{{ label() }}</SubmitButton
        >
        <InputError :message="form.errors.date" />
    </div>
</template>

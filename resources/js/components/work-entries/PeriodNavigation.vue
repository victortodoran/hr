<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import MonthPicker from '@/components/MonthPicker.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatDate } from '@/lib/dates';
import type { WorkEntryPeriod } from '@/types';

/**
 * Chooses the Period the Work Entries week page shows. Week steps a week
 * at a time with "This week", Month uses the month picker, future months
 * included, and Custom takes a From and a To date, with no arrows.
 * Switching mode stays near what is shown: the new Period holds today when
 * the old one does, or else its first day, and Custom starts on the
 * Period shown. Emits the URL query asking for the chosen Period.
 */
const props = defineProps<{
    period: WorkEntryPeriod;
    today: string;
    /** Stops at today: no later week or month, and no date after today. */
    noFuture?: boolean;
}>();

const emit = defineEmits<{
    change: [query: Record<string, string>];
}>();

const modes: { mode: WorkEntryPeriod['mode']; label: string }[] = [
    { mode: 'week', label: 'Week' },
    { mode: 'month', label: 'Month' },
    { mode: 'custom', label: 'Custom' },
];

/** The day a new Period is chosen around: today when shown, or else the first day. */
const anchor = computed(() =>
    props.period.from <= props.today && props.today <= props.period.to
        ? props.today
        : props.period.from,
);

function chooseMode(mode: WorkEntryPeriod['mode']): void {
    if (mode === props.period.mode) {
        return;
    }

    emit(
        'change',
        {
            week: { week: anchor.value },
            month: { month: anchor.value.slice(0, 7) },
            custom: { from: props.period.from, to: props.period.to },
        }[mode],
    );
}

/**
 * The date a number of days away, as a YYYY-MM-DD date.
 */
function addDays(date: string, days: number): string {
    const moved = new Date(`${date}T00:00:00`);
    moved.setDate(moved.getDate() + days);

    return moved.toLocaleDateString('en-CA');
}

const isCurrentWeek = computed(
    () => props.today >= props.period.from && props.today <= props.period.to,
);

const from = ref(props.period.from);
const to = ref(props.period.to);

watch(
    () => props.period,
    (period) => {
        from.value = period.from;
        to.value = period.to;
    },
);

/** Why the custom period asked for last was refused, if it was. */
const errors = computed(() => usePage().props.errors);
</script>

<template>
    <div class="flex flex-wrap items-center gap-3">
        <div
            class="flex rounded-md border p-0.5"
            role="group"
            aria-label="Period"
        >
            <Button
                v-for="choice in modes"
                :key="choice.mode"
                :variant="choice.mode === period.mode ? 'secondary' : 'ghost'"
                size="sm"
                :aria-pressed="choice.mode === period.mode"
                @click="chooseMode(choice.mode)"
                >{{ choice.label }}</Button
            >
        </div>

        <div v-if="period.mode === 'week'" class="flex items-center gap-2">
            <Button
                variant="outline"
                size="icon"
                aria-label="Previous week"
                @click="emit('change', { week: addDays(period.from, -7) })"
            >
                <ChevronLeft />
            </Button>
            <span class="text-sm font-medium">
                {{ formatDate(period.from) }} – {{ formatDate(period.to) }}
            </span>
            <Button
                variant="outline"
                size="icon"
                aria-label="Next week"
                :disabled="noFuture && period.to >= today"
                @click="emit('change', { week: addDays(period.from, 7) })"
            >
                <ChevronRight />
            </Button>
            <Button
                v-if="!isCurrentWeek"
                variant="ghost"
                size="sm"
                @click="emit('change', { week: today })"
                >This week</Button
            >
        </div>

        <MonthPicker
            v-else-if="period.mode === 'month'"
            :month="period.from.slice(0, 7)"
            :allow-future="!noFuture"
            @change="(month) => emit('change', { month })"
        />

        <form
            v-else
            class="flex flex-wrap items-end gap-2"
            @submit.prevent="emit('change', { from, to })"
        >
            <div class="grid gap-1">
                <Label for="period-from">From</Label>
                <Input
                    id="period-from"
                    v-model="from"
                    type="date"
                    :max="noFuture ? today : undefined"
                    class="w-40"
                    required
                />
                <InputError :message="errors.from" />
            </div>
            <div class="grid gap-1">
                <Label for="period-to">To</Label>
                <Input
                    id="period-to"
                    v-model="to"
                    type="date"
                    :max="noFuture ? today : undefined"
                    class="w-40"
                    required
                />
                <InputError :message="errors.to" />
            </div>
            <Button type="submit" variant="outline">Show</Button>
        </form>
    </div>
</template>

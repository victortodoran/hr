<script setup lang="ts">
import type { DateValue } from '@internationalized/date';
import { parseDate } from '@internationalized/date';
import type { DateRange } from 'reka-ui';
import {
    DateRangePickerAnchor,
    DateRangePickerField,
    DateRangePickerInput,
    DateRangePickerRoot,
} from 'reka-ui';
import { computed } from 'vue';
import {
    PickerCalendar,
    PickerTrigger,
    pickerFieldClass,
} from '@/components/date-picker';
import { countDays, formatDateRange } from '@/lib/dates';

/** `id` lands on a hidden input that moves focus to the field, so `<Label for>` works. */
defineOptions({ inheritAttrs: false });

const props = defineProps<{
    id: string;
    /** YYYY-MM-DD. */
    min?: string;
    /** YYYY-MM-DD. */
    max?: string;
    weekdaysOnly?: boolean;
    required?: boolean;
    invalid?: boolean;
}>();

const start = defineModel<string>('start', { default: '' });
const end = defineModel<string>('end', { default: '' });

const range = computed({
    get: (): DateRange => ({
        start: start.value ? parseDate(start.value) : undefined,
        end: end.value ? parseDate(end.value) : undefined,
    }),
    set: (chosen: DateRange) => {
        start.value = chosen.start ? chosen.start.toString() : '';
        end.value = chosen.end ? chosen.end.toString() : '';
    },
});

const minValue = computed((): DateValue | undefined =>
    props.min ? parseDate(props.min) : undefined,
);
const maxValue = computed((): DateValue | undefined =>
    props.max ? parseDate(props.max) : undefined,
);

const isComplete = computed(
    () => start.value !== '' && end.value !== '' && start.value <= end.value,
);

const dayCount = computed(() => {
    const days = countDays(start.value, end.value, props.weekdaysOnly);
    const unit = props.weekdaysOnly ? 'weekday' : 'day';

    return `${days} ${unit}${days === 1 ? '' : 's'}`;
});
</script>

<template>
    <div class="grid gap-1">
        <DateRangePickerRoot
            :id="id"
            v-model="range"
            :min-value="minValue"
            :max-value="maxValue"
            :required="required"
            close-on-select
        >
            <DateRangePickerAnchor as-child>
                <DateRangePickerField
                    v-slot="{ segments }"
                    v-bind="$attrs"
                    data-slot="date-range-picker"
                    :aria-invalid="invalid || undefined"
                    :class="pickerFieldClass"
                >
                    <PickerTrigger range />
                    <div
                        class="peer flex items-center tabular-nums"
                        :class="
                            isComplete && 'opacity-0 focus-within:opacity-100'
                        "
                    >
                        <template
                            v-for="type in ['start', 'end'] as const"
                            :key="type"
                        >
                            <span
                                v-if="type === 'end'"
                                class="px-1 text-subtle-foreground"
                                >–</span
                            >
                            <template
                                v-for="(segment, index) in segments[type]"
                                :key="`${type}-${segment.part}-${index}`"
                            >
                                <DateRangePickerInput
                                    v-if="segment.part === 'literal'"
                                    :part="segment.part"
                                    :type="type"
                                    class="text-subtle-foreground"
                                    >{{ segment.value }}</DateRangePickerInput
                                >
                                <DateRangePickerInput
                                    v-else
                                    :part="segment.part"
                                    :type="type"
                                    class="rounded-sm px-0.5 outline-none focus:bg-primary-soft data-placeholder:text-subtle-foreground"
                                    >{{ segment.value }}</DateRangePickerInput
                                >
                            </template>
                        </template>
                    </div>
                    <span
                        v-if="isComplete"
                        aria-hidden="true"
                        class="pointer-events-none absolute inset-y-0 left-9 flex items-center peer-focus-within:hidden"
                        >{{ formatDateRange(start, end) }}</span
                    >
                </DateRangePickerField>
            </DateRangePickerAnchor>

            <PickerCalendar range />
        </DateRangePickerRoot>
        <p
            v-if="isComplete"
            data-test="date-range-count"
            class="text-caption text-muted-foreground"
        >
            {{ dayCount }}
        </p>
    </div>
</template>

<script setup lang="ts">
import type { DateValue } from '@internationalized/date';
import { parseDate } from '@internationalized/date';
import { CalendarDays, ChevronLeft, ChevronRight } from '@lucide/vue';
import type { DateRange } from 'reka-ui';
import {
    DateRangePickerAnchor,
    DateRangePickerCalendar,
    DateRangePickerCell,
    DateRangePickerCellTrigger,
    DateRangePickerContent,
    DateRangePickerField,
    DateRangePickerGrid,
    DateRangePickerGridBody,
    DateRangePickerGridHead,
    DateRangePickerGridRow,
    DateRangePickerHeadCell,
    DateRangePickerHeader,
    DateRangePickerHeading,
    DateRangePickerInput,
    DateRangePickerNext,
    DateRangePickerPrev,
    DateRangePickerRoot,
    DateRangePickerTrigger,
} from 'reka-ui';
import { computed } from 'vue';
import { buttonVariants } from '@/components/ui/button';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { countDays, formatDateSpan } from '@/lib/dates';
import { cn } from '@/lib/utils';

/**
 * A first and a last date in one field (rule K14): type both, or pick the
 * first and then the last day from the calendar the icon opens. Once
 * chosen and left, it reads like every range (C9), "Nov 30 – Dec 4, 2026",
 * with the days counted under it: "3 days", or "5 weekdays" with
 * `weekdaysOnly`, skipping Saturdays and Sundays. The models are the two
 * dates as YYYY-MM-DD, '' when empty. `id` lands on a hidden input that
 * moves focus to the field, so `<Label for>` works.
 */
const props = defineProps<{
    id: string;
    /** The earliest date that can be chosen, as YYYY-MM-DD. */
    min?: string;
    /** The latest date that can be chosen, as YYYY-MM-DD. */
    max?: string;
    /** Counts only Mondays to Fridays, as "5 weekdays". */
    weekdaysOnly?: boolean;
    disabled?: boolean;
    required?: boolean;
    /** Marks the field as refused, such as when its error shows. */
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

/** The days chosen as people read them, such as "5 weekdays". */
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
            :disabled="disabled"
            :required="required"
            close-on-select
        >
            <DateRangePickerAnchor as-child>
                <DateRangePickerField
                    v-slot="{ segments }"
                    data-slot="date-range-picker"
                    :aria-invalid="invalid || undefined"
                    class="relative flex h-9 w-full min-w-0 items-center gap-1 rounded-md border border-border-strong bg-background pr-3 pl-1 text-base transition-[color,box-shadow] focus-within:border-primary focus-within:ring-[3px] focus-within:ring-ring/25 aria-invalid:border-destructive aria-invalid:ring-destructive/15 data-disabled:cursor-not-allowed data-disabled:opacity-50 data-invalid:border-destructive md:text-body"
                >
                    <Tooltip>
                        <TooltipTrigger as-child>
                            <DateRangePickerTrigger
                                :class="
                                    cn(
                                        buttonVariants({
                                            variant: 'ghost',
                                            size: 'icon-sm',
                                        }),
                                        'size-7 text-muted-foreground',
                                    )
                                "
                                aria-label="Open calendar"
                            >
                                <CalendarDays class="size-4" />
                            </DateRangePickerTrigger>
                        </TooltipTrigger>
                        <TooltipContent>Open calendar</TooltipContent>
                    </Tooltip>
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
                        >{{ formatDateSpan(start, end) }}</span
                    >
                </DateRangePickerField>
            </DateRangePickerAnchor>

            <DateRangePickerContent
                align="start"
                :side-offset="4"
                class="z-50 rounded-xl border bg-popover p-3 text-popover-foreground shadow-popover outline-hidden"
            >
                <DateRangePickerCalendar
                    v-slot="{ weekDays, grid }"
                    weekday-format="short"
                    class="grid gap-2"
                >
                    <DateRangePickerHeader
                        class="flex items-center justify-between"
                    >
                        <Tooltip>
                            <TooltipTrigger as-child>
                                <DateRangePickerPrev
                                    :class="
                                        buttonVariants({
                                            variant: 'ghost',
                                            size: 'icon-sm',
                                        })
                                    "
                                    aria-label="Previous month"
                                >
                                    <ChevronLeft class="size-4" />
                                </DateRangePickerPrev>
                            </TooltipTrigger>
                            <TooltipContent>Previous month</TooltipContent>
                        </Tooltip>
                        <DateRangePickerHeading class="text-body font-medium" />
                        <Tooltip>
                            <TooltipTrigger as-child>
                                <DateRangePickerNext
                                    :class="
                                        buttonVariants({
                                            variant: 'ghost',
                                            size: 'icon-sm',
                                        })
                                    "
                                    aria-label="Next month"
                                >
                                    <ChevronRight class="size-4" />
                                </DateRangePickerNext>
                            </TooltipTrigger>
                            <TooltipContent>Next month</TooltipContent>
                        </Tooltip>
                    </DateRangePickerHeader>
                    <DateRangePickerGrid
                        v-for="month in grid"
                        :key="month.value.toString()"
                        class="border-collapse"
                    >
                        <DateRangePickerGridHead>
                            <DateRangePickerGridRow class="flex">
                                <DateRangePickerHeadCell
                                    v-for="day in weekDays"
                                    :key="day"
                                    class="w-9 text-caption font-normal text-muted-foreground"
                                    >{{ day }}</DateRangePickerHeadCell
                                >
                            </DateRangePickerGridRow>
                        </DateRangePickerGridHead>
                        <DateRangePickerGridBody>
                            <DateRangePickerGridRow
                                v-for="(week, weekIndex) in month.rows"
                                :key="`week-${weekIndex}`"
                                class="mt-1 flex"
                            >
                                <DateRangePickerCell
                                    v-for="day in week"
                                    :key="day.toString()"
                                    :date="day"
                                    class="p-0"
                                >
                                    <DateRangePickerCellTrigger
                                        :day="day"
                                        :month="month.value"
                                        class="flex size-9 items-center justify-center rounded-md text-body tabular-nums outline-none hover:bg-accent focus-visible:ring-2 focus-visible:ring-ring data-disabled:pointer-events-none data-disabled:text-subtle-foreground data-highlighted:not-data-selection-start:not-data-selection-end:bg-primary-soft data-outside-view:text-subtle-foreground data-selected:not-data-selection-start:not-data-selection-end:bg-primary-soft data-selection-end:bg-primary data-selection-end:text-primary-foreground data-selection-start:bg-primary data-selection-start:text-primary-foreground data-today:font-semibold data-today:not-data-selection-start:not-data-selection-end:text-primary data-unavailable:line-through"
                                    />
                                </DateRangePickerCell>
                            </DateRangePickerGridRow>
                        </DateRangePickerGridBody>
                    </DateRangePickerGrid>
                </DateRangePickerCalendar>
            </DateRangePickerContent>
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

<script setup lang="ts">
import type { DateValue } from '@internationalized/date';
import { parseDate } from '@internationalized/date';
import { CalendarDays, ChevronLeft, ChevronRight } from '@lucide/vue';
import {
    DatePickerAnchor,
    DatePickerCalendar,
    DatePickerCell,
    DatePickerCellTrigger,
    DatePickerContent,
    DatePickerField,
    DatePickerGrid,
    DatePickerGridBody,
    DatePickerGridHead,
    DatePickerGridRow,
    DatePickerHeadCell,
    DatePickerHeader,
    DatePickerHeading,
    DatePickerInput,
    DatePickerNext,
    DatePickerPrev,
    DatePickerRoot,
    DatePickerTrigger,
} from 'reka-ui';
import { computed, ref } from 'vue';
import { buttonVariants } from '@/components/ui/button';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { formatDate } from '@/lib/dates';
import { cn } from '@/lib/utils';

/**
 * A date field (rule K14): type the month, day and year into the field, or
 * pick a day from the calendar the icon opens. Once chosen and left, it
 * reads like every date (C9), "Nov 20, 2026". The model is the date as
 * YYYY-MM-DD, or '' when empty. `id` lands on a hidden date input that
 * moves focus to the field, so `<Label for>` works. Other attributes, such
 * as `class`, go to the field.
 */
defineOptions({ inheritAttrs: false });

const props = defineProps<{
    id: string;
    /** The earliest date that can be chosen, as YYYY-MM-DD. */
    min?: string;
    /** The latest date that can be chosen, as YYYY-MM-DD. */
    max?: string;
    disabled?: boolean;
    required?: boolean;
    /** Marks the field as refused, such as when its error shows. */
    invalid?: boolean;
}>();

const date = defineModel<string>({ default: '' });

const value = computed({
    get: (): DateValue | undefined =>
        date.value ? parseDate(date.value) : undefined,
    set: (chosen: DateValue | undefined) => {
        date.value = chosen ? chosen.toString() : '';
    },
});

const minValue = computed(() => (props.min ? parseDate(props.min) : undefined));
const maxValue = computed(() => (props.max ? parseDate(props.max) : undefined));

const open = ref(false);
</script>

<template>
    <DatePickerRoot
        :id="id"
        v-model="value"
        v-model:open="open"
        :min-value="minValue"
        :max-value="maxValue"
        :disabled="disabled"
        :required="required"
        close-on-select
    >
        <DatePickerAnchor as-child>
            <DatePickerField
                v-slot="{ segments }"
                v-bind="$attrs"
                data-slot="date-picker"
                :aria-invalid="invalid || undefined"
                class="relative flex h-9 w-full min-w-0 items-center gap-1 rounded-md border border-border-strong bg-background pr-3 pl-1 text-base transition-[color,box-shadow] focus-within:border-primary focus-within:ring-[3px] focus-within:ring-ring/25 aria-invalid:border-destructive aria-invalid:ring-destructive/15 data-disabled:cursor-not-allowed data-disabled:opacity-50 data-invalid:border-destructive md:text-body"
            >
                <Tooltip>
                    <TooltipTrigger as-child>
                        <DatePickerTrigger
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
                        </DatePickerTrigger>
                    </TooltipTrigger>
                    <TooltipContent>Open calendar</TooltipContent>
                </Tooltip>
                <div
                    class="peer flex items-center tabular-nums"
                    :class="date && 'opacity-0 focus-within:opacity-100'"
                >
                    <template
                        v-for="(segment, index) in segments"
                        :key="`${segment.part}-${index}`"
                    >
                        <DatePickerInput
                            v-if="segment.part === 'literal'"
                            :part="segment.part"
                            class="text-subtle-foreground"
                            >{{ segment.value }}</DatePickerInput
                        >
                        <DatePickerInput
                            v-else
                            :part="segment.part"
                            class="rounded-sm px-0.5 outline-none focus:bg-primary-soft data-placeholder:text-subtle-foreground"
                            >{{ segment.value }}</DatePickerInput
                        >
                    </template>
                </div>
                <span
                    v-if="date"
                    aria-hidden="true"
                    class="pointer-events-none absolute inset-y-0 left-9 flex items-center peer-focus-within:hidden"
                    >{{ formatDate(date) }}</span
                >
            </DatePickerField>
        </DatePickerAnchor>

        <DatePickerContent
            align="start"
            :side-offset="4"
            class="z-50 rounded-xl border bg-popover p-3 text-popover-foreground shadow-popover outline-hidden"
        >
            <DatePickerCalendar
                v-slot="{ weekDays, grid }"
                weekday-format="short"
                class="grid gap-2"
            >
                <DatePickerHeader class="flex items-center justify-between">
                    <Tooltip>
                        <TooltipTrigger as-child>
                            <DatePickerPrev
                                :class="
                                    buttonVariants({
                                        variant: 'ghost',
                                        size: 'icon-sm',
                                    })
                                "
                                aria-label="Previous month"
                            >
                                <ChevronLeft class="size-4" />
                            </DatePickerPrev>
                        </TooltipTrigger>
                        <TooltipContent>Previous month</TooltipContent>
                    </Tooltip>
                    <DatePickerHeading class="text-body font-medium" />
                    <Tooltip>
                        <TooltipTrigger as-child>
                            <DatePickerNext
                                :class="
                                    buttonVariants({
                                        variant: 'ghost',
                                        size: 'icon-sm',
                                    })
                                "
                                aria-label="Next month"
                            >
                                <ChevronRight class="size-4" />
                            </DatePickerNext>
                        </TooltipTrigger>
                        <TooltipContent>Next month</TooltipContent>
                    </Tooltip>
                </DatePickerHeader>
                <DatePickerGrid
                    v-for="month in grid"
                    :key="month.value.toString()"
                    class="border-collapse"
                >
                    <DatePickerGridHead>
                        <DatePickerGridRow class="flex">
                            <DatePickerHeadCell
                                v-for="day in weekDays"
                                :key="day"
                                class="w-9 text-caption font-normal text-muted-foreground"
                                >{{ day }}</DatePickerHeadCell
                            >
                        </DatePickerGridRow>
                    </DatePickerGridHead>
                    <DatePickerGridBody>
                        <DatePickerGridRow
                            v-for="(week, weekIndex) in month.rows"
                            :key="`week-${weekIndex}`"
                            class="mt-1 flex"
                        >
                            <DatePickerCell
                                v-for="day in week"
                                :key="day.toString()"
                                :date="day"
                                class="p-0"
                            >
                                <DatePickerCellTrigger
                                    :day="day"
                                    :month="month.value"
                                    class="flex size-9 items-center justify-center rounded-md text-body tabular-nums outline-none hover:bg-accent focus-visible:ring-2 focus-visible:ring-ring data-disabled:pointer-events-none data-disabled:text-subtle-foreground data-outside-view:text-subtle-foreground data-selected:bg-primary data-selected:text-primary-foreground data-today:font-semibold data-today:not-data-selected:text-primary data-unavailable:line-through"
                                />
                            </DatePickerCell>
                        </DatePickerGridRow>
                    </DatePickerGridBody>
                </DatePickerGrid>
            </DatePickerCalendar>
        </DatePickerContent>
    </DatePickerRoot>
</template>

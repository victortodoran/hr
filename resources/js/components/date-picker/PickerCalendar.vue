<script setup lang="ts">
import { ChevronLeft, ChevronRight } from '@lucide/vue';
import {
    DatePickerCalendar,
    DatePickerCell,
    DatePickerCellTrigger,
    DatePickerContent,
    DatePickerGrid,
    DatePickerGridBody,
    DatePickerGridHead,
    DatePickerGridRow,
    DatePickerHeadCell,
    DatePickerHeader,
    DatePickerHeading,
    DatePickerNext,
    DatePickerPrev,
    DateRangePickerCalendar,
    DateRangePickerCell,
    DateRangePickerCellTrigger,
    DateRangePickerContent,
    DateRangePickerGrid,
    DateRangePickerGridBody,
    DateRangePickerGridHead,
    DateRangePickerGridRow,
    DateRangePickerHeadCell,
    DateRangePickerHeader,
    DateRangePickerHeading,
    DateRangePickerNext,
    DateRangePickerPrev,
} from 'reka-ui';
import { computed } from 'vue';
import IconButton from '@/components/IconButton.vue';

const props = defineProps<{
    range?: boolean;
}>();

const single = {
    Content: DatePickerContent,
    Calendar: DatePickerCalendar,
    Header: DatePickerHeader,
    Heading: DatePickerHeading,
    Prev: DatePickerPrev,
    Next: DatePickerNext,
    Grid: DatePickerGrid,
    GridHead: DatePickerGridHead,
    GridBody: DatePickerGridBody,
    GridRow: DatePickerGridRow,
    HeadCell: DatePickerHeadCell,
    Cell: DatePickerCell,
    CellTrigger: DatePickerCellTrigger,
};

const ranged = {
    Content: DateRangePickerContent,
    Calendar: DateRangePickerCalendar,
    Header: DateRangePickerHeader,
    Heading: DateRangePickerHeading,
    Prev: DateRangePickerPrev,
    Next: DateRangePickerNext,
    Grid: DateRangePickerGrid,
    GridHead: DateRangePickerGridHead,
    GridBody: DateRangePickerGridBody,
    GridRow: DateRangePickerGridRow,
    HeadCell: DateRangePickerHeadCell,
    Cell: DateRangePickerCell,
    CellTrigger: DateRangePickerCellTrigger,
};

const part = computed(() => (props.range ? ranged : single));

const dayClass = computed(() =>
    props.range
        ? 'data-highlighted:not-data-selection-start:not-data-selection-end:bg-primary-soft data-selected:not-data-selection-start:not-data-selection-end:bg-primary-soft data-selection-end:bg-primary data-selection-end:text-primary-foreground data-selection-start:bg-primary data-selection-start:text-primary-foreground data-today:not-data-selection-start:not-data-selection-end:text-primary'
        : 'data-selected:bg-primary data-selected:text-primary-foreground data-today:not-data-selected:text-primary',
);
</script>

<template>
    <component
        :is="part.Content"
        align="start"
        :side-offset="4"
        class="z-50 rounded-xl border bg-popover p-3 text-popover-foreground shadow-popover outline-hidden"
    >
        <component
            :is="part.Calendar"
            v-slot="{ weekDays, grid }"
            weekday-format="short"
            class="grid gap-2"
        >
            <component
                :is="part.Header"
                class="flex items-center justify-between"
            >
                <component :is="part.Prev" as-child>
                    <IconButton label="Previous month"
                        ><ChevronLeft class="size-4"
                    /></IconButton>
                </component>
                <component :is="part.Heading" class="text-body font-medium" />
                <component :is="part.Next" as-child>
                    <IconButton label="Next month"
                        ><ChevronRight class="size-4"
                    /></IconButton>
                </component>
            </component>
            <component
                :is="part.Grid"
                v-for="month in grid"
                :key="month.value.toString()"
                class="border-collapse"
            >
                <component :is="part.GridHead">
                    <component :is="part.GridRow" class="flex">
                        <component
                            :is="part.HeadCell"
                            v-for="day in weekDays"
                            :key="day"
                            class="w-9 text-caption font-normal text-muted-foreground"
                            >{{ day }}</component
                        >
                    </component>
                </component>
                <component :is="part.GridBody">
                    <component
                        :is="part.GridRow"
                        v-for="(week, weekIndex) in month.rows"
                        :key="`week-${weekIndex}`"
                        class="mt-1 flex"
                    >
                        <component
                            :is="part.Cell"
                            v-for="day in week"
                            :key="day.toString()"
                            :date="day"
                            class="p-0"
                        >
                            <component
                                :is="part.CellTrigger"
                                :day="day"
                                :month="month.value"
                                class="flex size-9 items-center justify-center rounded-md text-body tabular-nums outline-none hover:bg-accent focus-visible:ring-2 focus-visible:ring-ring data-disabled:pointer-events-none data-disabled:text-subtle-foreground data-outside-view:text-subtle-foreground data-today:font-semibold data-unavailable:line-through"
                                :class="dayClass"
                            />
                        </component>
                    </component>
                </component>
            </component>
        </component>
    </component>
</template>

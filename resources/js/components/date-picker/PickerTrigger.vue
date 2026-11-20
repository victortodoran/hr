<script setup lang="ts">
import { CalendarDays } from '@lucide/vue';
import { DatePickerTrigger, DateRangePickerTrigger } from 'reka-ui';
import IconButton from '@/components/IconButton.vue';

/**
 * The calendar icon inside a DatePicker's or DateRangePicker's field that
 * opens its calendar, named in a Tooltip (rule F20). It looks small inside
 * the field, but takes a 44px touch on phone (F15).
 */
defineProps<{
    /** Opens a DateRangePicker's calendar instead of a DatePicker's. */
    range?: boolean;
}>();
</script>

<template>
    <component
        :is="range ? DateRangePickerTrigger : DatePickerTrigger"
        as-child
    >
        <IconButton
            label="Open calendar"
            class="relative size-7 text-muted-foreground max-sm:after:absolute max-sm:after:-inset-2"
        >
            <CalendarDays class="size-4" />
        </IconButton>
    </component>
</template>

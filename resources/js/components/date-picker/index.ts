export { default as PickerCalendar } from './PickerCalendar.vue';
export { default as PickerTrigger } from './PickerTrigger.vue';

/**
 * The look of a DatePicker's or DateRangePicker's field, an input's border
 * and focus ring around typed segments, with room on the left for the
 * calendar trigger.
 */
export const pickerFieldClass =
    'relative flex h-9 w-full min-w-0 items-center gap-1 rounded-md border border-border-strong bg-background pr-3 pl-1 text-base transition-[color,box-shadow] focus-within:border-primary focus-within:ring-[3px] focus-within:ring-ring/25 aria-invalid:border-destructive aria-invalid:ring-destructive/15 data-disabled:cursor-not-allowed data-disabled:opacity-50 data-invalid:border-destructive md:text-body';

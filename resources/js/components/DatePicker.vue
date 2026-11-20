<script setup lang="ts">
import type { DateValue } from '@internationalized/date';
import { parseDate } from '@internationalized/date';
import {
    DatePickerAnchor,
    DatePickerField,
    DatePickerInput,
    DatePickerRoot,
} from 'reka-ui';
import { computed, ref } from 'vue';
import {
    PickerCalendar,
    PickerTrigger,
    pickerFieldClass,
} from '@/components/date-picker';
import { formatDate } from '@/lib/dates';

/** `id` lands on a hidden date input that moves focus to the field, so `<Label for>` works. */
defineOptions({ inheritAttrs: false });

const props = defineProps<{
    id: string;
    /** YYYY-MM-DD. */
    min?: string;
    /** YYYY-MM-DD. */
    max?: string;
    disabled?: boolean;
    required?: boolean;
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
                :class="pickerFieldClass"
            >
                <PickerTrigger />
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

        <PickerCalendar />
    </DatePickerRoot>
</template>

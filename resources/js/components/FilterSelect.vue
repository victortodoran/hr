<script setup lang="ts">
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { FilterChoice } from '@/types';

/**
 * One of a list's dropdowns, for a `FilterBar`: the choices it filters
 * by, led by "all" labelled `any` (such as "Any state") when the list can
 * also show every one. `label` names it for screen readers and tests.
 */
withDefaults(
    defineProps<{
        modelValue: string;
        label: string;
        choices: FilterChoice[];
        /** The label of the "all" choice; left out when one must be chosen. */
        any?: string;
        /** The trigger's width, as a Tailwind class. */
        width?: string;
    }>(),
    { any: undefined, width: 'w-44' },
);

const emit = defineEmits<{
    'update:modelValue': [value: string];
}>();
</script>

<template>
    <Select
        :model-value="modelValue"
        @update:model-value="emit('update:modelValue', String($event))"
    >
        <SelectTrigger :class="width" :aria-label="label">
            <SelectValue />
        </SelectTrigger>
        <SelectContent>
            <SelectItem v-if="any" value="all">{{ any }}</SelectItem>
            <SelectItem
                v-for="choice in choices"
                :key="choice.value"
                :value="choice.value"
                >{{ choice.label }}</SelectItem
            >
        </SelectContent>
    </Select>
</template>

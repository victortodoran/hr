<script setup lang="ts">
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { FilterChoice } from '@/types';

withDefaults(
    defineProps<{
        modelValue: string;
        label: string;
        choices: FilterChoice[];
        any?: string;
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

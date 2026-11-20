<script setup lang="ts">
import { computed } from 'vue';
import SegmentedControl from '@/components/SegmentedControl.vue';
import { scopes } from '@/lib/roles';
import type { Scope } from '@/types';

const props = defineProps<{
    label: string;
    allowed: Scope[];
    disabled?: boolean;
}>();

const model = defineModel<Scope | 'none'>({ required: true });

const options = computed(() =>
    [
        { value: 'none' as const, label: 'None', help: 'not in this Role' },
        ...scopes,
    ].map((option) => {
        const allowed =
            option.value === 'none' || props.allowed.includes(option.value);

        return {
            value: option.value,
            label: option.label,
            disabled: !allowed,
            tooltip: allowed
                ? `${option.label}: ${option.help}`
                : `${option.label} isn't possible for ${props.label}`,
        };
    }),
);
</script>

<template>
    <SegmentedControl
        v-model="model"
        :options="options"
        :label="`Scope for ${label}`"
        :disabled="disabled"
        size="sm"
        fill-on-phone
        class="shrink-0"
    />
</template>

<script setup lang="ts">
import { computed } from 'vue';
import SegmentedControl from '@/components/SegmentedControl.vue';
import { scopes } from '@/lib/roles';
import type { Scope } from '@/types';

/**
 * A scoped Permission's row in the Role editor's matrix: a SegmentedControl
 * of all four Scopes, None · Own · Reports · Everyone (rule V8), where None
 * leaves the Permission out of the Role and the Scopes the Permission can't
 * take are disabled. Each option says what it means in a Tooltip. On a
 * phone it spans the row, with 44px targets.
 */
const props = defineProps<{
    /** The Permission's name, which labels the group. */
    label: string;
    /** The Scopes the Permission can be given with. */
    allowed: Scope[];
    disabled?: boolean;
}>();

/** The Scope the Role gives the Permission with, or 'none' when it leaves it out. */
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

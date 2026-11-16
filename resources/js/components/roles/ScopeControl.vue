<script setup lang="ts">
import { RadioGroupItem, RadioGroupRoot } from 'reka-ui';
import { scopes } from '@/lib/roles';
import type { Scope } from '@/types';

/**
 * A scoped Permission's Scope as a segmented control: a real radio group of
 * Own, Reports and Everyone, those the Permission can't take disabled.
 */
defineProps<{
    /** The Permission's name, which labels the group. */
    label: string;
    /** The Scopes the Permission can be given with. */
    allowed: Scope[];
    disabled: boolean;
}>();

const model = defineModel<Scope | null>({ required: true });
</script>

<template>
    <RadioGroupRoot
        v-model="model"
        :disabled="disabled"
        :aria-label="`Scope for ${label}`"
        orientation="horizontal"
        class="inline-flex shrink-0 rounded-md border bg-muted/40 p-0.5 aria-disabled:opacity-50"
    >
        <RadioGroupItem
            v-for="scope in scopes"
            :key="scope.value"
            :value="scope.value"
            :disabled="disabled || !allowed.includes(scope.value)"
            :title="
                allowed.includes(scope.value)
                    ? `${scope.label}: ${scope.help}`
                    : `${scope.label} isn't possible: nobody may reopen their own Work Entries`
            "
            class="min-h-9 rounded-[5px] px-2.5 text-xs font-medium text-muted-foreground transition-colors hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50 data-[state=checked]:bg-background data-[state=checked]:text-foreground data-[state=checked]:shadow-sm"
            >{{ scope.label }}</RadioGroupItem
        >
    </RadioGroupRoot>
</template>

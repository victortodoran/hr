<script setup lang="ts">
import { Button } from '@/components/ui/button';

/**
 * Above a list whose rows can be ticked: how many are selected, "Select all
 * N matching" when offered, "Clear" once something is selected, and the
 * list's bulk actions in the `actions` slot.
 */
defineProps<{
    count: number;
    /** How many rows match the filters, offered as "Select all"; none hides it. */
    matching?: number;
}>();

const emit = defineEmits<{
    selectAll: [];
    clear: [];
}>();
</script>

<template>
    <div class="flex flex-wrap items-center justify-between gap-3 text-sm">
        <div class="flex items-center gap-3">
            <span class="text-muted-foreground">{{ count }} selected</span>
            <Button
                v-if="matching"
                variant="link"
                size="sm"
                class="px-0"
                @click="emit('selectAll')"
                >Select all {{ matching }} matching</Button
            >
            <Button
                v-if="count > 0"
                variant="link"
                size="sm"
                class="px-0"
                @click="emit('clear')"
                >Clear</Button
            >
        </div>
        <div class="flex gap-2">
            <slot name="actions" />
        </div>
    </div>
</template>

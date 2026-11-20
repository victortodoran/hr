<script setup lang="ts">
import { Button } from '@/components/ui/button';

/**
 * Appears once a row is ticked (rule K9): floating at the bottom centre,
 * it says "N selected · total", offers "Select all N matching" when more
 * match than are ticked, Clear, and the list's bulk actions in the
 * `actions` slot, the last of them the screen's one `primary` button (B2).
 * On a phone it becomes a full-width bar stuck to the bottom (P8), with
 * just the count and the actions, whose labels the page shortens there.
 */
defineProps<{
    count: number;
    /** How many rows match the filters, offered as "Select all"; none hides it. */
    matching?: number;
    /** What the selection adds up to, such as "45 h". */
    total?: string;
}>();

const emit = defineEmits<{
    selectAll: [];
    clear: [];
}>();
</script>

<template>
    <div v-if="count > 0" class="contents">
        <div
            data-slot="selection-bar"
            role="region"
            aria-label="Selected rows"
            class="sticky bottom-4 z-20 mx-auto flex w-fit max-w-full items-center gap-2 rounded-xl border bg-surface-raised py-2 pr-2 pl-4 shadow-overlay max-sm:fixed max-sm:inset-x-0 max-sm:bottom-0 max-sm:w-full max-sm:rounded-none max-sm:border-x-0 max-sm:border-b-0 max-sm:pb-[max(0.5rem,env(safe-area-inset-bottom))]"
        >
            <span
                class="text-body whitespace-nowrap tabular-nums max-sm:mr-auto"
                ><span class="font-semibold">{{ count }} selected</span
                ><template v-if="total"> · {{ total }}</template></span
            >
            <Button
                v-if="matching && matching > count"
                variant="link"
                size="sm"
                class="px-1 max-sm:hidden"
                @click="emit('selectAll')"
                >Select all {{ matching }} matching</Button
            >
            <span class="mx-1 h-5 w-px bg-border max-sm:hidden" />
            <Button
                variant="ghost"
                size="sm"
                class="max-sm:hidden"
                @click="emit('clear')"
                >Clear</Button
            >
            <div class="flex items-center gap-2 max-sm:ml-auto">
                <slot name="actions" />
            </div>
        </div>
        <div class="h-16 sm:hidden" aria-hidden="true" />
    </div>
</template>

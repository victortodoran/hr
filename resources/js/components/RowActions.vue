<script setup lang="ts">
import { Ellipsis } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';

/**
 * A row's actions (rule K2): one or two frequent ones as `ghost` icon
 * buttons in the default slot, and the rest in the ⋯ menu's `menu` slot,
 * destructive items last. On a pointer screen they show while the row is
 * hovered or holds focus, so keyboards still reach them. Each icon button
 * names itself in a Tooltip (rule F20).
 */
defineProps<{
    /** Names the row, for the ⋯ button, such as "Cara Lane's Work Entry on Nov 10". */
    label: string;
}>();

defineSlots<{
    default?: () => unknown;
    menu?: () => unknown;
}>();
</script>

<template>
    <div
        data-slot="row-actions"
        class="flex items-center justify-end gap-1 transition-opacity sm:opacity-0 sm:group-hover/row:opacity-100 sm:focus-within:opacity-100 sm:has-[[aria-expanded=true]]:opacity-100"
    >
        <slot />
        <DropdownMenu v-if="$slots.menu">
            <Tooltip>
                <DropdownMenuTrigger as-child>
                    <TooltipTrigger as-child>
                        <Button
                            variant="ghost"
                            size="icon-sm"
                            :aria-label="`More for ${label}`"
                        >
                            <Ellipsis />
                        </Button>
                    </TooltipTrigger>
                </DropdownMenuTrigger>
                <TooltipContent>More</TooltipContent>
            </Tooltip>
            <DropdownMenuContent align="end">
                <slot name="menu" />
            </DropdownMenuContent>
        </DropdownMenu>
    </div>
</template>

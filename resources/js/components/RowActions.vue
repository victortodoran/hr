<script setup lang="ts">
import { Ellipsis } from '@lucide/vue';
import { ref } from 'vue';
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

/** On a pointer screen they show while the row is hovered or holds focus, so keyboards still reach them. */
defineProps<{
    label: string;
}>();

defineSlots<{
    default?: () => unknown;
    menu?: () => unknown;
}>();

/** The menu is anchored to the button by hand: reka-ui would otherwise anchor it to the Tooltip in between. */
const trigger = ref<InstanceType<typeof Button> | null>(null);
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
                            ref="trigger"
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
            <DropdownMenuContent
                align="end"
                :reference="trigger?.$el as HTMLElement | undefined"
            >
                <slot name="menu" />
            </DropdownMenuContent>
        </DropdownMenu>
    </div>
</template>

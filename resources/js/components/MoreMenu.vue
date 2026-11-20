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

/**
 * A detail header's ⋯ menu (rule L12): an icon button that names itself in
 * a Tooltip (F20), opening the slot's menu items, destructive ones last.
 * On a phone the button is a 44px target (P6).
 */
defineProps<{
    /** Names the button and its Tooltip, such as "More for this Project". */
    label: string;
}>();

defineSlots<{
    default: () => unknown;
}>();

/**
 * The ⋯ button. Its Tooltip sits between it and the menu, so the menu is
 * anchored to it by hand: reka-ui would otherwise anchor it to the Tooltip.
 */
const trigger = ref<InstanceType<typeof Button> | null>(null);
</script>

<template>
    <DropdownMenu>
        <Tooltip>
            <DropdownMenuTrigger as-child>
                <TooltipTrigger as-child>
                    <Button
                        ref="trigger"
                        type="button"
                        variant="secondary"
                        size="icon"
                        class="max-sm:size-11 max-sm:flex-none!"
                        :aria-label="label"
                    >
                        <Ellipsis />
                    </Button>
                </TooltipTrigger>
            </DropdownMenuTrigger>
            <TooltipContent>{{ label }}</TooltipContent>
        </Tooltip>
        <DropdownMenuContent
            align="end"
            :reference="trigger?.$el as HTMLElement | undefined"
        >
            <slot />
        </DropdownMenuContent>
    </DropdownMenu>
</template>

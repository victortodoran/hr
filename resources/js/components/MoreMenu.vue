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

defineProps<{
    label: string;
}>();

defineSlots<{
    default: () => unknown;
}>();

/** The menu is anchored to the button by hand: reka-ui would otherwise anchor it to the Tooltip in between. */
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

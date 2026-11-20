<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
</script>

<template>
    <Tooltip>
        <TooltipTrigger as-child>
            <Badge variant="outline"
                >Default<span class="sr-only"
                    >: given to every new Employee</span
                ></Badge
            >
        </TooltipTrigger>
        <TooltipContent>Given to every new Employee</TooltipContent>
    </Tooltip>
</template>

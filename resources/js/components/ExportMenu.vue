<script setup lang="ts">
import { Download } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';

withDefaults(
    defineProps<{
        fileUrl: (format: 'csv' | 'xlsx') => string;
        disabled?: boolean;
        label?: string;
    }>(),
    { disabled: false, label: 'Export' },
);
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger as-child>
            <Button variant="secondary" :disabled="disabled">
                <Download />
                {{ label }}
            </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end">
            <DropdownMenuItem as-child>
                <a :href="fileUrl('csv')" download>CSV</a>
            </DropdownMenuItem>
            <DropdownMenuItem as-child>
                <a :href="fileUrl('xlsx')" download>Excel</a>
            </DropdownMenuItem>
        </DropdownMenuContent>
    </DropdownMenu>
</template>

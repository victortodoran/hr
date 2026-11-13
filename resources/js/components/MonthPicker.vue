<script setup lang="ts">
import { ChevronLeft, ChevronRight } from '@lucide/vue';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { addMonths, formatMonth, today } from '@/lib/dates';

/**
 * A month, such as "‹ November 2026 ›": the arrows step one month back or
 * forward, the label opens a month-and-year picker, and "This month"
 * returns to the current one. Months after the current one can't be
 * chosen. Emits the chosen month as YYYY-MM.
 */
const props = defineProps<{
    /** The month shown, as YYYY-MM. */
    month: string;
}>();

const emit = defineEmits<{
    change: [month: string];
}>();

const currentMonth = today().slice(0, 7);
const currentYear = Number(currentMonth.slice(0, 4));

const isCurrentMonth = computed(() => props.month >= currentMonth);

/** The year the picker shows, starting on the month's own. */
const pickerYear = ref(Number(props.month.slice(0, 4)));

const monthsOfPickerYear = computed(() =>
    Array.from({ length: 12 }, (_, index) => {
        const month = `${pickerYear.value}-${String(index + 1).padStart(2, '0')}`;

        return {
            month,
            label: new Date(`${month}-01T00:00:00`).toLocaleDateString(
                undefined,
                { month: 'short' },
            ),
        };
    }),
);

function openPicker(isOpen: boolean): void {
    if (isOpen) {
        pickerYear.value = Number(props.month.slice(0, 4));
    }
}
</script>

<template>
    <div class="flex items-center gap-2">
        <Button
            variant="outline"
            size="icon"
            aria-label="Previous month"
            @click="emit('change', addMonths(month, -1))"
        >
            <ChevronLeft />
        </Button>
        <DropdownMenu @update:open="openPicker">
            <DropdownMenuTrigger as-child>
                <Button variant="ghost" class="min-w-36 font-medium">{{
                    formatMonth(month)
                }}</Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent class="w-64 p-2" align="center">
                <div class="mb-2 flex items-center justify-between">
                    <Button
                        variant="ghost"
                        size="icon"
                        aria-label="Previous year"
                        @click="pickerYear--"
                    >
                        <ChevronLeft />
                    </Button>
                    <span class="text-sm font-medium tabular-nums">{{
                        pickerYear
                    }}</span>
                    <Button
                        variant="ghost"
                        size="icon"
                        aria-label="Next year"
                        :disabled="pickerYear >= currentYear"
                        @click="pickerYear++"
                    >
                        <ChevronRight />
                    </Button>
                </div>
                <div class="grid grid-cols-3 gap-1">
                    <DropdownMenuItem
                        v-for="choice in monthsOfPickerYear"
                        :key="choice.month"
                        :disabled="choice.month > currentMonth"
                        :class="[
                            'justify-center',
                            choice.month === month &&
                                'bg-accent font-medium text-accent-foreground',
                        ]"
                        @select="emit('change', choice.month)"
                        >{{ choice.label }}</DropdownMenuItem
                    >
                </div>
            </DropdownMenuContent>
        </DropdownMenu>
        <Button
            variant="outline"
            size="icon"
            aria-label="Next month"
            :disabled="isCurrentMonth"
            @click="emit('change', addMonths(month, 1))"
        >
            <ChevronRight />
        </Button>
        <Button
            v-if="!isCurrentMonth"
            variant="ghost"
            size="sm"
            @click="emit('change', currentMonth)"
            >This month</Button
        >
    </div>
</template>

<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import type { InertiaLinkProps } from '@inertiajs/vue3';
import { computed } from 'vue';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';

/**
 * Hours per period as bars, without a chart library (rule K18): grayscale
 * bars sized against the busiest period, the current one in `primary`, the
 * Pending part of each bar hatched on top, a one-letter axis label under
 * each, and a legend with the totals. Each bar names its values in a
 * Tooltip and its `aria-label` and, given an `href`, opens that period; an
 * sr-only table lists the values for screen readers.
 */
const props = defineProps<{
    bars: {
        /** The period's full name, such as "November 2026". */
        label: string;
        /** Its axis label, such as "N". */
        axisLabel: string;
        /** Every counted value, Pending included. */
        value: number;
        /** How much of the value is still Pending. */
        pending: number;
        /** Whether this is the current period, drawn in `primary`. */
        current: boolean;
        href?: InertiaLinkProps['href'];
    }[];
    /** What the sr-only table holds, such as "Hours each month of 2026". */
    caption: string;
    /** Its period column's heading, such as "Month". */
    periodHeading: string;
    /** A value as people read it, such as "7.5 h". */
    format: (value: number) => string;
    /** A line after the legend, such as "Declined not counted". */
    note?: string;
}>();

/** The busiest period's value, which a full-height bar stands for. */
const most = computed(() => Math.max(...props.bars.map((bar) => bar.value), 0));

const pendingTotal = computed(() =>
    props.bars.reduce((sum, bar) => sum + bar.pending, 0),
);

const approvedTotal = computed(
    () =>
        props.bars.reduce((sum, bar) => sum + bar.value, 0) -
        pendingTotal.value,
);

/** A part's share of the busiest period, as a CSS height. */
function heightOf(part: number): string {
    return most.value === 0 ? '0%' : `${(part / most.value) * 100}%`;
}

/** What a bar says, such as "November 2026: 96 h, 36 h Pending". */
function describe(bar: (typeof props.bars)[number]): string {
    return [
        `${bar.label}: ${props.format(bar.value)}`,
        bar.pending > 0 && `${props.format(bar.pending)} Pending`,
    ]
        .filter(Boolean)
        .join(', ');
}
</script>

<template>
    <figure data-slot="mini-bar-chart" class="flex flex-col gap-2">
        <ol class="flex h-32 items-stretch gap-1 border-b sm:gap-2">
            <li
                v-for="bar in bars"
                :key="bar.label"
                class="flex min-w-0 flex-1"
            >
                <Tooltip>
                    <TooltipTrigger as-child>
                        <component
                            :is="bar.href === undefined ? 'span' : Link"
                            :href="bar.href"
                            :aria-label="describe(bar)"
                            class="group flex w-full flex-col justify-end rounded-t-sm focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                            data-test="mini-bar"
                        >
                            <span
                                v-if="bar.value === 0"
                                class="h-0.5 w-full bg-border"
                            />
                            <span
                                v-else
                                class="flex w-full flex-col overflow-hidden rounded-t-sm transition-opacity group-hover:opacity-80"
                                :style="{ height: heightOf(bar.value) }"
                            >
                                <span
                                    class="w-full bg-hatched"
                                    :class="
                                        bar.current
                                            ? 'bg-primary-soft text-primary'
                                            : 'bg-muted text-foreground/70'
                                    "
                                    :style="{
                                        height: `${(bar.pending / bar.value) * 100}%`,
                                    }"
                                />
                                <span
                                    class="w-full flex-1"
                                    :class="
                                        bar.current
                                            ? 'bg-primary'
                                            : 'bg-foreground/70'
                                    "
                                />
                            </span>
                        </component>
                    </TooltipTrigger>
                    <TooltipContent>{{ describe(bar) }}</TooltipContent>
                </Tooltip>
            </li>
        </ol>
        <div class="flex gap-1 sm:gap-2" aria-hidden="true">
            <span
                v-for="bar in bars"
                :key="bar.label"
                class="min-w-0 flex-1 text-center text-caption"
                :class="
                    bar.current
                        ? 'font-semibold text-foreground'
                        : 'text-muted-foreground'
                "
                >{{ bar.axisLabel }}</span
            >
        </div>
        <figcaption
            class="flex flex-wrap items-center gap-x-4 gap-y-1 text-caption text-muted-foreground"
        >
            <span class="flex items-center gap-1.5">
                <span
                    class="size-2.5 rounded-sm bg-foreground/70"
                    aria-hidden="true"
                />
                Approved
                <span class="text-foreground tabular-nums">{{
                    format(approvedTotal)
                }}</span>
            </span>
            <span class="flex items-center gap-1.5">
                <span
                    class="size-2.5 rounded-sm bg-muted bg-hatched text-foreground/70"
                    aria-hidden="true"
                />
                Pending
                <span class="text-foreground tabular-nums">{{
                    format(pendingTotal)
                }}</span>
            </span>
            <span v-if="note">{{ note }}</span>
        </figcaption>
        <table class="sr-only">
            <caption>
                {{ caption }}
            </caption>
            <thead>
                <tr>
                    <th scope="col">{{ periodHeading }}</th>
                    <th scope="col">Hours</th>
                    <th scope="col">Pending</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="bar in bars" :key="bar.label">
                    <th scope="row">{{ bar.label }}</th>
                    <td>{{ format(bar.value) }}</td>
                    <td>{{ format(bar.pending) }}</td>
                </tr>
            </tbody>
        </table>
    </figure>
</template>

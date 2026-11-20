<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import type { InertiaLinkProps } from '@inertiajs/vue3';
import { computed } from 'vue';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { formatHours } from '@/lib/work-entries';

const props = defineProps<{
    bars: {
        label: string;
        axisLabel: string;
        /** Pending included. */
        hours: number;
        pendingHours: number;
        current: boolean;
        href?: InertiaLinkProps['href'];
    }[];
    caption: string;
    note?: string;
}>();

const most = computed(() => Math.max(...props.bars.map((bar) => bar.hours), 0));

const pendingTotal = computed(() =>
    props.bars.reduce((sum, bar) => sum + bar.pendingHours, 0),
);

const approvedTotal = computed(
    () =>
        props.bars.reduce((sum, bar) => sum + bar.hours, 0) -
        pendingTotal.value,
);

function heightOf(part: number): string {
    return most.value === 0 ? '0%' : `${(part / most.value) * 100}%`;
}

function describe(bar: (typeof props.bars)[number]): string {
    return [
        `${bar.label}: ${formatHours(bar.hours)}`,
        bar.pendingHours > 0 && `${formatHours(bar.pendingHours)} Pending`,
    ]
        .filter(Boolean)
        .join(', ');
}
</script>

<template>
    <figure data-slot="hours-bar-chart" class="flex flex-col gap-2">
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
                            data-test="hours-bar"
                        >
                            <span
                                v-if="bar.hours === 0"
                                class="h-0.5 w-full bg-border"
                            />
                            <span
                                v-else
                                class="flex w-full flex-col overflow-hidden rounded-t-sm transition-opacity group-hover:opacity-80"
                                :style="{ height: heightOf(bar.hours) }"
                            >
                                <span
                                    class="w-full bg-hatched"
                                    :class="
                                        bar.current
                                            ? 'bg-primary-soft text-primary'
                                            : 'bg-muted text-foreground/70'
                                    "
                                    :style="{
                                        height: `${(bar.pendingHours / bar.hours) * 100}%`,
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
                    formatHours(approvedTotal)
                }}</span>
            </span>
            <span class="flex items-center gap-1.5">
                <span
                    class="size-2.5 rounded-sm bg-muted bg-hatched text-foreground/70"
                    aria-hidden="true"
                />
                Pending
                <span class="text-foreground tabular-nums">{{
                    formatHours(pendingTotal)
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
                    <th scope="col">Month</th>
                    <th scope="col">Hours</th>
                    <th scope="col">Pending</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="bar in bars" :key="bar.label">
                    <th scope="row">{{ bar.label }}</th>
                    <td>{{ formatHours(bar.hours) }}</td>
                    <td>{{ formatHours(bar.pendingHours) }}</td>
                </tr>
            </tbody>
        </table>
    </figure>
</template>

<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { index as myPerformanceEvaluations } from '@/routes/my-performance-evaluations';
import { index as performanceEvaluations } from '@/routes/performance-evaluations';
import { index as timeOff } from '@/routes/time-off';
import { index as timeOffList } from '@/routes/time-off-list';
import { index as travelRequestList } from '@/routes/travel-request-list';
import { index as travelRequests } from '@/routes/travel-requests';
import { index as workEntries } from '@/routes/work-entries';
import { index as workEntryList } from '@/routes/work-entry-list';
import type { ScopedArea } from '@/types';

/**
 * Moves between the Employee's own page of an area and the list across the
 * Employees they may see, labelled by how far that reaches (rule L19).
 * Hidden when only one applies.
 */
const props = defineProps<{
    area: ScopedArea;
}>();

const page = usePage();
const { isCurrentUrl } = useCurrentUrl();

const pages = {
    work: { mine: workEntries(), others: workEntryList() },
    timeOff: { mine: timeOff(), others: timeOffList() },
    travel: { mine: travelRequests(), others: travelRequestList() },
    performance: {
        mine: myPerformanceEvaluations(),
        others: performanceEvaluations(),
    },
} as const;

const options = computed(() => {
    const auth = page.props.auth;
    const reach = auth.othersReach[props.area];
    const hasOwnPage = props.area !== 'work' || auth.canSeeWorkEntries;

    return [
        ...(hasOwnPage
            ? [{ label: 'Mine', href: pages[props.area].mine }]
            : []),
        ...(reach
            ? [
                  {
                      label: reach === 'everyone' ? 'Everyone' : 'Reports',
                      href: pages[props.area].others,
                  },
              ]
            : []),
    ];
});
</script>

<template>
    <nav
        v-if="options.length > 1"
        aria-label="Whose records"
        data-slot="scope-switch"
        class="inline-flex gap-0.5 self-start rounded-lg bg-muted p-0.75"
    >
        <Link
            v-for="option in options"
            :key="option.label"
            :href="option.href"
            :aria-current="isCurrentUrl(option.href) ? 'page' : undefined"
            class="inline-flex h-7.5 items-center rounded-md px-3 text-meta font-medium whitespace-nowrap text-muted-foreground outline-none hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring aria-[current=page]:bg-background aria-[current=page]:text-foreground aria-[current=page]:ring-1 aria-[current=page]:ring-border"
        >
            {{ option.label }}
        </Link>
    </nav>
</template>

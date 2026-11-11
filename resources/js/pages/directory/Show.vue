<script setup lang="ts">
import { Head, Link, setLayoutProps } from '@inertiajs/vue3';
import { computed } from 'vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { index, show } from '@/routes/directory';
import { index as assignments } from '@/routes/employees/assignments';
import type { ChainLink } from '@/types';

const props = defineProps<{
    chain: ChainLink[];
    canViewAssignments: boolean;
}>();

const employee = computed(() => props.chain[0]);

/**
 * The chain arrives from the Employee up to the top Employee; the diagram
 * draws it the other way round, with the top Employee first.
 */
const chainFromTop = computed(() => [...props.chain].reverse());

/**
 * Whether the link is the Employee whose Reporting Chain this is.
 */
function isViewed(link: ChainLink): boolean {
    return link.id === employee.value.id;
}

setLayoutProps({
    breadcrumbs: [
        { title: 'Directory', href: index() },
        { title: employee.value.name, href: show(employee.value.id) },
    ],
});
</script>

<template>
    <Head :title="`${employee.name}'s Reporting Chain`" />

    <div class="flex max-w-xl flex-col gap-6 p-4">
        <div class="flex items-start justify-between gap-4">
            <Heading
                :title="`${employee.name}'s Reporting Chain`"
                description="Who they report to, up to the top of the company"
            />
            <Button v-if="canViewAssignments" variant="outline" as-child>
                <Link :href="assignments(employee.id)">Assignments</Link>
            </Button>
        </div>

        <ol class="flex flex-col items-center" aria-label="Reporting Chain">
            <li
                v-for="(link, position) in chainFromTop"
                :key="link.id"
                class="flex w-full flex-col items-center"
            >
                <div
                    v-if="position > 0"
                    class="h-6 w-px bg-border"
                    aria-hidden="true"
                />
                <component
                    :is="isViewed(link) ? 'div' : Link"
                    :href="isViewed(link) ? undefined : show(link.id)"
                    :aria-current="isViewed(link) ? 'page' : undefined"
                    class="w-full max-w-xs rounded-lg border px-4 py-3 text-center"
                    :class="
                        isViewed(link)
                            ? 'border-primary bg-primary/5 ring-1 ring-primary'
                            : 'transition-colors hover:bg-accent'
                    "
                >
                    <p class="font-medium">{{ link.name }}</p>
                    <p class="text-sm text-muted-foreground">
                        {{ link.department }}
                    </p>
                    <p
                        v-if="position === 0"
                        class="mt-1 text-xs text-muted-foreground"
                    >
                        Top Employee
                    </p>
                </component>
            </li>
        </ol>
    </div>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { usePageTitle } from '@/composables/usePageTitle';
import { SidebarTrigger } from '@/components/ui/sidebar';
import type { BreadcrumbItem } from '@/types';

const props = withDefaults(
    defineProps<{
        breadcrumbs?: BreadcrumbItem[];
    }>(),
    {
        breadcrumbs: () => [],
    },
);

/** The pages above this one; the page's own name is its heading (rule L20). */
const ancestors = computed(() => props.breadcrumbs.slice(0, -1));

const heading = usePageTitle();

/** The page's `<h1>`, shown beside the menu on a phone (rule P2). */
const pageTitle = computed(
    () => heading.value ?? props.breadcrumbs.at(-1)?.title,
);
</script>

<template>
    <header
        class="flex h-14 shrink-0 items-center gap-2 border-b border-border px-2 sm:h-13 sm:px-5"
    >
        <SidebarTrigger class="size-9 sm:size-7" />
        <span
            v-if="pageTitle"
            class="truncate text-section sm:hidden"
            data-test="top-bar-title"
            >{{ pageTitle }}</span
        >
        <Breadcrumbs
            v-if="ancestors.length > 0"
            class="max-sm:hidden"
            :breadcrumbs="ancestors"
        />
    </header>
</template>

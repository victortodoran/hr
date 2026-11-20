<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { toUrl } from '@/lib/utils';
import type { NavItem } from '@/types';

/**
 * The settings template's frame (rule L16): a 200px sub-nav of text links
 * with a marker on the open one, beside a content column of at most
 * 880px. Below 1024px the sub-nav becomes a row above the content.
 */
defineProps<{
    items: NavItem[];
    /** Names the sub-nav for screen readers, such as "Company settings". */
    label: string;
}>();

const { isCurrentOrParentUrl } = useCurrentUrl();
</script>

<template>
    <div
        data-slot="settings-frame"
        class="grid items-start gap-6 lg:grid-cols-[200px_minmax(0,1fr)] lg:gap-10"
    >
        <nav
            :aria-label="label"
            class="flex [scrollbar-width:none] gap-1 overflow-x-auto lg:flex-col"
        >
            <Link
                v-for="item in items"
                :key="toUrl(item.href)"
                :href="item.href"
                :aria-current="
                    isCurrentOrParentUrl(item.href) ? 'page' : undefined
                "
                class="flex h-9 shrink-0 items-center rounded-md border-l-2 border-transparent px-3 text-body whitespace-nowrap text-muted-foreground transition-colors hover:bg-accent hover:text-foreground aria-[current=page]:border-primary aria-[current=page]:bg-accent aria-[current=page]:font-medium aria-[current=page]:text-foreground max-lg:rounded-b-none max-lg:border-b-2 max-lg:border-l-0"
                >{{ item.title }}</Link
            >
        </nav>
        <div class="max-w-4xl min-w-0">
            <slot />
        </div>
    </div>
</template>

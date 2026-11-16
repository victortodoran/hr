<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import type { Paginated } from '@/types';

/**
 * Previous and Next links for a paginated list. Lists whose page holds state
 * across pages, such as ticked rows, keep it with `preserveState`.
 */
defineProps<{
    page: Paginated<unknown>;
    preserveState?: boolean;
}>();
</script>

<template>
    <div
        v-if="page.prev_page_url || page.next_page_url"
        class="flex items-center justify-between gap-4 text-sm text-muted-foreground"
    >
        <span> {{ page.from }}–{{ page.to }} of {{ page.total }} </span>
        <div class="flex gap-2">
            <Button
                v-for="pageLink in [
                    { label: 'Previous', url: page.prev_page_url },
                    { label: 'Next', url: page.next_page_url },
                ]"
                :key="pageLink.label"
                variant="outline"
                size="sm"
                :disabled="!pageLink.url"
                :as="pageLink.url ? Link : 'button'"
                :href="pageLink.url ?? undefined"
                :preserve-state="pageLink.url ? preserveState : undefined"
            >
                {{ pageLink.label }}
            </Button>
        </div>
    </div>
</template>

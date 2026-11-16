<script setup lang="ts">
/**
 * Previous and Next for a paginated list. Turning a page keeps the page's
 * state, such as which rows are selected.
 */
import { Link } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import type { Paginated } from '@/types';

defineProps<{
    page: Paginated<unknown>;
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
                :preserve-state="pageLink.url ? true : undefined"
            >
                {{ pageLink.label }}
            </Button>
        </div>
    </div>
</template>

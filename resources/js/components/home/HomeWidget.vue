<script setup lang="ts">
import type { InertiaLinkProps } from '@inertiajs/vue3';
import { ArrowRight } from '@lucide/vue';
import TextLink from '@/components/TextLink.vue';
import { Card } from '@/components/ui/card';

/**
 * One widget on Home (rules L9, L15): a Card with a `text-section` title,
 * an optional note after it in the `meta` slot, an optional link to the
 * page it summarises on the right, an optional one-line description, and
 * its rows or stats in the default slot.
 */
defineProps<{
    title: string;
    description?: string;
    /** Where the header link goes, such as the Approvals page. */
    href?: InertiaLinkProps['href'];
    /** The header link's words, such as "Open Approvals". */
    linkLabel?: string;
}>();

defineSlots<{
    default: () => unknown;
    meta?: () => unknown;
}>();
</script>

<template>
    <Card class="min-w-0 gap-3 p-4 sm:p-5">
        <header class="flex flex-col gap-1">
            <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                <h2 class="text-section">{{ title }}</h2>
                <slot name="meta" />
                <TextLink
                    v-if="href !== undefined && linkLabel !== undefined"
                    :href="href"
                    class="ml-auto inline-flex items-center gap-1 text-meta"
                    >{{ linkLabel }}
                    <ArrowRight class="size-3.5" aria-hidden="true"
                /></TextLink>
            </div>
            <p v-if="description" class="text-meta text-muted-foreground">
                {{ description }}
            </p>
        </header>
        <slot />
    </Card>
</template>

<script setup lang="ts">
import type { InertiaLinkProps } from '@inertiajs/vue3';
import { ArrowRight } from '@lucide/vue';
import TextLink from '@/components/TextLink.vue';
import { Card } from '@/components/ui/card';

defineProps<{
    title: string;
    description?: string;
    href?: InertiaLinkProps['href'];
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

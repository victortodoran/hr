<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import type { InertiaLinkProps } from '@inertiajs/vue3';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { getInitials } from '@/composables/useInitials';

defineProps<{
    name: string;
    detail?: string | null;
    href?: InertiaLinkProps['href'];
}>();
</script>

<template>
    <div data-slot="person-cell" class="flex min-w-0 items-center gap-2.5">
        <Avatar class="-my-1 size-7 shrink-0" aria-hidden="true">
            <AvatarFallback
                class="text-caption font-semibold text-muted-foreground"
                >{{ getInitials(name) }}</AvatarFallback
            >
        </Avatar>
        <div class="min-w-0">
            <Link
                v-if="href"
                :href="href"
                class="block truncate font-medium underline-offset-4 hover:underline"
                >{{ name }}</Link
            >
            <span v-else class="block truncate">{{ name }}</span>
            <span
                v-if="detail"
                class="block truncate text-meta font-normal text-muted-foreground"
                >{{ detail }}</span
            >
        </div>
    </div>
</template>

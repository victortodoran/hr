<script setup lang="ts">
import { useMediaQuery } from '@vueuse/core';
import AppContent from '@/components/AppContent.vue';
import AppShell from '@/components/AppShell.vue';
import AppSidebar from '@/components/AppSidebar.vue';
import AppSidebarHeader from '@/components/AppSidebarHeader.vue';
import { Toaster } from '@/components/ui/sonner';
import type { BreadcrumbItem } from '@/types';

type Props = {
    breadcrumbs?: BreadcrumbItem[];
};

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});

const isPhone = useMediaQuery('(max-width: 639px)');
</script>

<template>
    <AppShell>
        <AppSidebar />
        <AppContent class="min-w-0 overflow-x-clip">
            <AppSidebarHeader :breadcrumbs="breadcrumbs" />
            <slot />
        </AppContent>
        <Toaster
            close-button
            :duration="4000"
            :position="isPhone ? 'top-center' : 'bottom-right'"
        />
    </AppShell>
</template>

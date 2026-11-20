<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    Breadcrumb,
    BreadcrumbItem,
    BreadcrumbLink,
    BreadcrumbList,
    BreadcrumbPage,
    BreadcrumbSeparator,
} from '@/components/ui/breadcrumb';
import type { BreadcrumbItem as BreadcrumbItemType } from '@/types';

/**
 * The ancestors of the current page, each followed by a separator that
 * leads to the page's own heading (rule L20).
 */
defineProps<{
    breadcrumbs: BreadcrumbItemType[];
}>();
</script>

<template>
    <Breadcrumb>
        <BreadcrumbList>
            <template v-for="(item, index) in breadcrumbs" :key="index">
                <BreadcrumbItem>
                    <BreadcrumbLink v-if="item.href" as-child>
                        <Link :href="item.href">{{ item.title }}</Link>
                    </BreadcrumbLink>
                    <BreadcrumbPage v-else>{{ item.title }}</BreadcrumbPage>
                </BreadcrumbItem>
                <BreadcrumbSeparator />
            </template>
        </BreadcrumbList>
    </Breadcrumb>
</template>

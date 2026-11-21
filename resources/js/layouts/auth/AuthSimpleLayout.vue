<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import AppMark from '@/components/AppMark.vue';
import { home } from '@/routes';

defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <div
        class="flex min-h-svh flex-col items-center justify-center bg-surface px-4 py-10"
    >
        <main
            data-slot="auth-card"
            class="flex w-full max-w-100 flex-col gap-6 rounded-lg border border-border bg-background px-6 py-8 sm:px-8"
        >
            <div class="flex flex-col items-center gap-4 text-center">
                <Link :href="home()" class="rounded-md">
                    <AppMark />
                </Link>
                <div class="flex flex-col gap-1.5">
                    <h1 class="text-title-sm">{{ title }}</h1>
                    <p
                        v-if="description"
                        class="text-body text-muted-foreground"
                    >
                        {{ description }}
                    </p>
                </div>
            </div>
            <slot />
        </main>
    </div>
</template>

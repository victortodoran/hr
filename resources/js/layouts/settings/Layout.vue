<script setup lang="ts">
import PageContainer from '@/components/PageContainer.vue';
import PageHeader from '@/components/PageHeader.vue';

/**
 * Settings (rule L16): Security is its only section, so it shows right under
 * the header with no sub-nav.
 */
</script>

<template>
    <PageContainer width="full">
        <PageHeader
            title="Settings"
            description="The password you sign in with."
        />
        <section class="max-w-xl space-y-12">
            <slot />
        </section>
    </PageContainer>
</template>

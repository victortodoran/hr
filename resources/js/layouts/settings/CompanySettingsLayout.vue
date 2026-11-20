<script setup lang="ts">
import PageContainer from '@/components/PageContainer.vue';
import PageHeader from '@/components/PageHeader.vue';
import SettingsFrame from '@/components/SettingsFrame.vue';
import { useCompanySettingsLinks } from '@/composables/useCompanySettingsLinks';

/**
 * Company settings in the settings template (rule L16): the one page header
 * every part shares, and the sub-nav of the parts the signed-in Employee
 * may manage beside the open one.
 */
const settingsLinks = useCompanySettingsLinks();
</script>

<template>
    <PageContainer width="full">
        <PageHeader
            title="Company settings"
            description="Your company's details, its Departments, and the Roles that decide what people may do."
        />

        <SettingsFrame :items="settingsLinks" label="Company settings">
            <slot />
        </SettingsFrame>
    </PageContainer>
</template>

<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { watch } from 'vue';
import AppMark from '@/components/AppMark.vue';
import NavEmployee from '@/components/NavEmployee.vue';
import NavMain from '@/components/NavMain.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarSeparator,
    useSidebar,
} from '@/components/ui/sidebar';
import { useNavItems } from '@/composables/useNavItems';
import { landing } from '@/routes';

const page = usePage();

const { startItems, areaItems, companyItems } = useNavItems();

const { setOpenMobile } = useSidebar();

/** On a phone, the menu sheet closes once a link in it opens a page (rule P2). */
watch(
    () => page.url,
    () => setOpenMobile(false),
);
</script>

<template>
    <Sidebar collapsible="icon">
        <SidebarHeader class="px-3 pt-4">
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="landing()">
                            <AppMark />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent class="gap-0">
            <NavMain :items="startItems" />
            <SidebarSeparator class="mx-5 my-2.5" />
            <NavMain :items="areaItems" />
            <template v-if="companyItems.length > 0">
                <SidebarSeparator class="mx-5 my-2.5" />
                <NavMain label="Company" :items="companyItems" />
            </template>
        </SidebarContent>

        <SidebarFooter class="px-3 pb-3">
            <NavEmployee />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>

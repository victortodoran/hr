<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    BookOpen,
    BriefcaseBusiness,
    Briefcase,
    Building2,
    FolderKanban,
    FolderGit2,
    Landmark,
    LayoutGrid,
    ShieldCheck,
    Users,
} from '@lucide/vue';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavEmployee from '@/components/NavEmployee.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import { index as clients } from '@/routes/clients';
import { edit as company } from '@/routes/company';
import { index as departments } from '@/routes/departments';
import { index as directory } from '@/routes/directory';
import { index as assignments } from '@/routes/employees/assignments';
import { index as projects } from '@/routes/projects';
import { index as roles } from '@/routes/roles';
import type { NavItem } from '@/types';

const page = usePage();

const mainNavItems = computed<NavItem[]>(() => [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
    ...(page.props.auth.canSeeOwnAssignments
        ? [
              {
                  title: 'My Assignments',
                  href: assignments(page.props.auth.employee.id),
                  icon: BriefcaseBusiness,
              },
          ]
        : []),
    ...(page.props.auth.can.includes('view-directory')
        ? [{ title: 'Directory', href: directory(), icon: Users }]
        : []),
    ...(page.props.auth.can.includes('view-projects') ||
    page.props.auth.can.includes('manage-projects')
        ? [{ title: 'Projects', href: projects(), icon: FolderKanban }]
        : []),
    ...(page.props.auth.can.includes('manage-clients')
        ? [{ title: 'Clients', href: clients(), icon: Briefcase }]
        : []),
    ...(page.props.auth.can.includes('manage-departments')
        ? [{ title: 'Departments', href: departments(), icon: Building2 }]
        : []),
    ...(page.props.auth.can.includes('manage-roles')
        ? [{ title: 'Roles', href: roles(), icon: ShieldCheck }]
        : []),
    ...(page.props.auth.can.includes('manage-company')
        ? [{ title: 'Company', href: company(), icon: Landmark }]
        : []),
]);

const footerNavItems: NavItem[] = [
    {
        title: 'Repository',
        href: 'https://github.com/laravel/vue-starter-kit',
        icon: FolderGit2,
    },
    {
        title: 'Documentation',
        href: 'https://laravel.com/docs/starter-kits#vue',
        icon: BookOpen,
    },
];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavEmployee />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>

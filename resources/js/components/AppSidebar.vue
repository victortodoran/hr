<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    Briefcase,
    Clock,
    Building2,
    FolderKanban,
    Landmark,
    ShieldCheck,
    UserRound,
    UsersRound,
    Users,
} from '@lucide/vue';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
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
import { landing } from '@/routes';
import { index as clients } from '@/routes/clients';
import { edit as company } from '@/routes/company';
import { index as departments } from '@/routes/departments';
import { index as directory } from '@/routes/directory';
import { index as employees, show as employeePage } from '@/routes/employees';
import { index as projects } from '@/routes/projects';
import { index as roles } from '@/routes/roles';
import { index as workEntries } from '@/routes/work-entries';
import type { NavItem } from '@/types';

const page = usePage();

const mainNavItems = computed<NavItem[]>(() => [
    ...(page.props.auth.canSeeOwnPage
        ? [
              {
                  title: 'My Page',
                  href: employeePage(page.props.auth.employee.id),
                  icon: UserRound,
              },
          ]
        : []),
    ...(page.props.auth.canSeeWorkEntries
        ? [{ title: 'Work Entries', href: workEntries(), icon: Clock }]
        : []),
    ...(page.props.auth.canListEmployees
        ? [{ title: 'Employees', href: employees(), icon: UsersRound }]
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
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="landing()">
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
            <NavEmployee />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>

<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    Briefcase,
    CalendarDays,
    CheckCheck,
    Clock,
    Building2,
    FolderKanban,
    House,
    Plane,
    Receipt,
    Landmark,
    Network,
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
import { index as approvals } from '@/routes/approvals';
import { index as clients } from '@/routes/clients';
import { edit as company } from '@/routes/company';
import { index as departments } from '@/routes/departments';
import { index as directory } from '@/routes/directory';
import { index as employees, show as employeePage } from '@/routes/employees';
import { index as projects } from '@/routes/projects';
import { index as roles } from '@/routes/roles';
import { index as team } from '@/routes/team';
import { index as timeOff } from '@/routes/time-off';
import { index as travelExpenseList } from '@/routes/travel-expense-list';
import { index as travelRequests } from '@/routes/travel-requests';
import { index as workEntries } from '@/routes/work-entries';
import { index as workEntryList } from '@/routes/work-entry-list';
import type { NavItem } from '@/types';

const page = usePage();

const auth = computed(() => page.props.auth);

const meItems = computed<NavItem[]>(() => [
    { title: 'Home', href: landing(), icon: House },
    ...(auth.value.canSeeOwnPage
        ? [
              {
                  title: 'My Page',
                  href: employeePage(auth.value.employee.id),
                  icon: UserRound,
              },
          ]
        : []),
    ...(auth.value.canSeeWorkEntries
        ? [{ title: 'My Work Entries', href: workEntries(), icon: Clock }]
        : []),
    { title: 'Time Off', href: timeOff(), icon: CalendarDays },
    { title: 'Travel', href: travelRequests(), icon: Plane },
]);

const manageItems = computed<NavItem[]>(() => [
    ...(auth.value.canSeeTeam
        ? [{ title: 'Team', href: team(), icon: Network }]
        : []),
    ...(auth.value.canSeeApprovals
        ? [
              {
                  title: 'Approvals',
                  href: approvals(),
                  icon: CheckCheck,
                  badge: auth.value.approvalsWaiting,
              },
          ]
        : []),
    ...(auth.value.canSeeWorkEntryList
        ? [{ title: 'Work Entries', href: workEntryList(), icon: Clock }]
        : []),
    ...(auth.value.canSeeTravelExpenseList
        ? [
              {
                  title: 'Travel Expenses',
                  href: travelExpenseList(),
                  icon: Receipt,
              },
          ]
        : []),
]);

const companyItems = computed<NavItem[]>(() => [
    ...(auth.value.canListEmployees
        ? [{ title: 'Employees', href: employees(), icon: UsersRound }]
        : []),
    ...(auth.value.can.includes('view-directory')
        ? [{ title: 'Directory', href: directory(), icon: Users }]
        : []),
    ...(auth.value.can.includes('view-projects') ||
    auth.value.can.includes('manage-projects')
        ? [{ title: 'Projects', href: projects(), icon: FolderKanban }]
        : []),
    ...(auth.value.can.includes('manage-clients')
        ? [{ title: 'Clients', href: clients(), icon: Briefcase }]
        : []),
    ...(auth.value.can.includes('manage-departments')
        ? [{ title: 'Departments', href: departments(), icon: Building2 }]
        : []),
    ...(auth.value.can.includes('manage-roles')
        ? [{ title: 'Roles', href: roles(), icon: ShieldCheck }]
        : []),
    ...(auth.value.can.includes('manage-company')
        ? [{ title: 'Company', href: company(), icon: Landmark }]
        : []),
]);

/** The labelled groups, leaving out any with no link the Employee may open. */
const navGroups = computed(() =>
    [
        { label: 'Me', items: meItems.value },
        { label: 'Manage', items: manageItems.value },
        { label: 'Company', items: companyItems.value },
    ].filter((group) => group.items.length > 0),
);
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
            <NavMain
                v-for="group in navGroups"
                :key="group.label"
                :label="group.label"
                :items="group.items"
            />
        </SidebarContent>

        <SidebarFooter>
            <NavEmployee />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>

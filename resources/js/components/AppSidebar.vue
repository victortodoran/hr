<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    Briefcase,
    CalendarDays,
    Clock,
    Contact,
    FolderKanban,
    House,
    Inbox,
    Plane,
    SlidersHorizontal,
    Target,
    UsersRound,
} from '@lucide/vue';
import { computed } from 'vue';
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
} from '@/components/ui/sidebar';
import { useCompanySettingsLinks } from '@/composables/useCompanySettingsLinks';
import { landing } from '@/routes';
import { index as approvals } from '@/routes/approvals';
import { index as clients } from '@/routes/clients';
import { index as directory } from '@/routes/directory';
import { index as employees } from '@/routes/employees';
import { index as myPerformanceEvaluations } from '@/routes/my-performance-evaluations';
import { index as performanceEvaluations } from '@/routes/performance-evaluations';
import { index as projects } from '@/routes/projects';
import { index as timeOff } from '@/routes/time-off';
import { index as timeOffList } from '@/routes/time-off-list';
import { index as travelRequestList } from '@/routes/travel-request-list';
import { index as travelRequests } from '@/routes/travel-requests';
import { index as workEntries } from '@/routes/work-entries';
import { index as workEntryList } from '@/routes/work-entry-list';
import type { NavItem } from '@/types';

const page = usePage();

const auth = computed(() => page.props.auth);

const settingsLinks = useCompanySettingsLinks();

const startItems = computed<NavItem[]>(() => [
    { title: 'Home', href: landing(), icon: House },
    ...(auth.value.canSeeApprovals
        ? [
              {
                  title: 'Approvals',
                  href: approvals(),
                  icon: Inbox,
                  badge: auth.value.approvalsWaiting,
              },
          ]
        : []),
]);

/**
 * One entry per area (rule L18). Each opens the Employee's own page when
 * they have one, else the list across Employees; the ScopeSwitch on the
 * page moves between the two (rule L19).
 */
const areaItems = computed<NavItem[]>(() => {
    const work: NavItem[] = auth.value.canSeeWorkEntries
        ? [
              {
                  title: 'Work',
                  href: workEntries(),
                  icon: Clock,
                  activeOn: [workEntryList()],
              },
          ]
        : auth.value.canSeeWorkEntryList
          ? [{ title: 'Work', href: workEntryList(), icon: Clock }]
          : [];

    return [
        ...work,
        {
            title: 'Time Off',
            href: timeOff(),
            icon: CalendarDays,
            activeOn: [timeOffList()],
        },
        {
            title: 'Travel',
            href: travelRequests(),
            icon: Plane,
            activeOn: [travelRequestList()],
        },
        {
            title: 'Performance',
            href: myPerformanceEvaluations(),
            icon: Target,
            activeOn: [performanceEvaluations()],
        },
    ];
});

const companyItems = computed<NavItem[]>(() => [
    ...(auth.value.can.includes('view-directory') || auth.value.canSeeTeam
        ? [{ title: 'Directory', href: directory(), icon: Contact }]
        : []),
    ...(auth.value.canListEmployees
        ? [{ title: 'Employees', href: employees(), icon: UsersRound }]
        : []),
    ...(auth.value.can.includes('view-projects') ||
    auth.value.can.includes('manage-projects')
        ? [{ title: 'Projects', href: projects(), icon: FolderKanban }]
        : []),
    ...(auth.value.can.includes('manage-clients')
        ? [{ title: 'Clients', href: clients(), icon: Briefcase }]
        : []),
    ...(settingsLinks.value.length > 0
        ? [
              {
                  title: 'Company settings',
                  href: settingsLinks.value[0].href,
                  icon: SlidersHorizontal,
                  activeOn: settingsLinks.value.map((link) => link.href),
              },
          ]
        : []),
]);
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

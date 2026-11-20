import { usePage } from '@inertiajs/vue3';
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
import type { ComputedRef } from 'vue';
import { computed } from 'vue';
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

/** The sidebar and the command menu both read these, so they always agree on what the Employee may open. */
export function useNavItems(): {
    startItems: ComputedRef<NavItem[]>;
    areaItems: ComputedRef<NavItem[]>;
    companyItems: ComputedRef<NavItem[]>;
} {
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
        ...(auth.value.canSeeDirectory
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

    return { startItems, areaItems, companyItems };
}

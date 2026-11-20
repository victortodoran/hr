import { usePage } from '@inertiajs/vue3';
import type { ComputedRef } from 'vue';
import { computed } from 'vue';
import { edit as company } from '@/routes/company';
import { index as departments } from '@/routes/departments';
import { index as roles } from '@/routes/roles';
import type { NavItem } from '@/types';

export function useCompanySettingsLinks(): ComputedRef<NavItem[]> {
    const page = usePage();

    return computed(() => {
        const can = page.props.auth.can;

        return [
            ...(can.includes('manage-company')
                ? [{ title: 'Company', href: company() }]
                : []),
            ...(can.includes('manage-departments')
                ? [{ title: 'Departments', href: departments() }]
                : []),
            ...(can.includes('manage-roles')
                ? [{ title: 'Roles', href: roles() }]
                : []),
        ];
    });
}

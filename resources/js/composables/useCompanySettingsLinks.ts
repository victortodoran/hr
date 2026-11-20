import { usePage } from '@inertiajs/vue3';
import type { ComputedRef } from 'vue';
import { computed } from 'vue';
import { edit as company } from '@/routes/company';
import { index as departments } from '@/routes/departments';
import { index as roles } from '@/routes/roles';
import type { NavItem } from '@/types';

/**
 * The pages of Company settings the signed-in Employee may manage, in
 * sub-nav order: Company, Departments, Roles (rule L18 folds them into one
 * sidebar entry, which opens the first).
 */
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

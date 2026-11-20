import type { EmploymentStatus } from './employee-record';

export type Scope = 'own' | 'reports' | 'everyone';

/** Each Permission in a Role, mapped to its Scope or null when company-wide. */
export type Grants = Record<string, Scope | null>;

export type RoleHolder = {
    id: number;
    name: string;
    status: EmploymentStatus;
};

export type RoleRecord = {
    id: number;
    name: string;
    /** Whether this is Admin, which always holds every Permission. */
    is_admin: boolean;
    permissions: Grants;
    /** Whether every new Employee is given the Role. */
    given_to_new_employees: boolean;
    holders: RoleHolder[];
};

export type PermissionChoice = {
    value: string;
    label: string;
    /** The Scopes it can be given with, narrowest first; none when company-wide. */
    scopes: Scope[];
    /** The area it is about, such as "Work entries", in the Roles page's order. */
    group: string;
};

/** An Employee a Role can be given to: one who hasn't left. */
export type RoleCandidate = RoleHolder;

/** Whether the Roles page shows the selected Role, editable in place, or a new one being made. */
export type RolesPageMode = 'view' | 'create';

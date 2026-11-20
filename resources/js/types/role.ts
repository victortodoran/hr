import type { EmploymentStatus } from './employee-record';

export type Scope = 'own' | 'reports' | 'everyone';

export type Grants = Record<string, Scope | null>;

export type RoleHolder = {
    id: number;
    name: string;
    status: EmploymentStatus;
};

export type RoleRecord = {
    id: number;
    name: string;
    is_admin: boolean;
    permissions: Grants;
    given_to_new_employees: boolean;
    holders: RoleHolder[];
};

export type PermissionChoice = {
    value: string;
    label: string;
    /** The Scopes it can be given with, narrowest first; none when company-wide. */
    scopes: Scope[];
    group: string;
};

export type RoleCandidate = RoleHolder;

export type RolesPageMode = 'view' | 'create';

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
    permissions: Grants;
    holders: RoleHolder[];
};

export type PermissionChoice = {
    value: string;
    label: string;
    scoped: boolean;
};

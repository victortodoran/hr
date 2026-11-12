export type EmploymentStatus = 'future' | 'current' | 'former';

export type Choice = {
    id: number;
    name: string;
};

export type ManagerChoice = Choice & {
    status: EmploymentStatus;
};

export type EmployeeRecord = {
    id: number;
    name: string;
    email: string;
    department_id: number;
    manager_id: number | null;
    start_date: string;
    leaving_date: string | null;
    status: EmploymentStatus;
    is_top: boolean;
    can_resend_invitation: boolean;
    can_become_top: boolean;
};

/**
 * An Employee's record as their Employee page shows it, with the Manager
 * they effectively report to.
 */
export type EmployeePageRecord = {
    email: string;
    status: EmploymentStatus;
    start_date: string;
    leaving_date: string | null;
    department: string | null;
    manager: Choice | null;
    is_top: boolean;
};

/**
 * The tabs of an Employee's page: Overview (record and Reporting Chain),
 * Assignments & Pay, and Time Off (Paid Days and Time Off).
 */
export type EmployeePageTab = 'overview' | 'assignments' | 'time-off';

/**
 * One row of the Employees list.
 */
export type EmployeeListEntry = Choice & {
    status: EmploymentStatus;
    department: string | null;
    manager: Choice | null;
};

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
    can_resend_invitation: boolean;
};

/**
 * An Employee's record as their Employee page shows it, with the Manager
 * they effectively report to.
 */
export type EmployeePageRecord = {
    email: string;
    start_date: string;
    leaving_date: string | null;
    department: string | null;
    manager: Choice | null;
};

/**
 * Where an Employee stands, shown in their page's header on every tab:
 * their employment status.
 */
export type EmployeeStanding = {
    status: EmploymentStatus;
};

/**
 * The tabs of an Employee's page: Overview (record and Reporting Chain),
 * Assignments & Pay, Time Off (Paid Days and Time Off), and Travel (Travel
 * Requests and Travel Expenses).
 */
export type EmployeePageTab =
    | 'overview'
    | 'assignments'
    | 'time-off'
    | 'travel';

/**
 * One row of the Employees list.
 */
export type EmployeeListEntry = Choice & {
    status: EmploymentStatus;
    department: string | null;
    manager: Choice | null;
};

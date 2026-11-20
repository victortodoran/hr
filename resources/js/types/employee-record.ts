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

export type EmployeePageRecord = {
    email: string;
    start_date: string;
    leaving_date: string | null;
    department: string | null;
    manager: Choice | null;
};

export type ReportNeedingAManager = Choice & {
    can_edit: boolean;
};

export type EmployeeStanding = {
    status: EmploymentStatus;
};

export type EmployeePageTab =
    | 'overview'
    | 'assignments'
    | 'time-off'
    | 'work'
    | 'travel'
    | 'performance';

export type EmployeeListEntry = Choice & {
    job_title: string | null;
    status: EmploymentStatus;
    /** As YYYY-MM-DD. */
    start_date: string;
    department: string | null;
    manager: Choice | null;
};

export type EmploymentStatusCounts = Record<'all' | EmploymentStatus, number>;

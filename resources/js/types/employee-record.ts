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
};

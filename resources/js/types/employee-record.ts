export type Choice = {
    id: number;
    name: string;
};

export type EmployeeRecord = {
    id: number;
    name: string;
    email: string;
    department_id: number;
    manager_id: number | null;
    start_date: string;
    is_top: boolean;
    has_pending_invitation: boolean;
};

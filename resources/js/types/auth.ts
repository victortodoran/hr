export type Employee = {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

export type Auth = {
    employee: Employee;
    /** Company-wide Permissions the signed-in Employee holds. */
    can: string[];
    /** Whether the signed-in Employee may open their own Employee page. */
    canSeeOwnPage: boolean;
    /** Whether the Employees list holds more than the signed-in Employee. */
    canListEmployees: boolean;
    /** Whether the signed-in Employee may see any Work Entries. */
    canSeeWorkEntries: boolean;
    /** Whether the signed-in Employee has Direct Reports, for the Team page. */
    canSeeTeam: boolean;
    /** Whether the signed-in Employee may open the Approvals page. */
    canSeeApprovals: boolean;
};

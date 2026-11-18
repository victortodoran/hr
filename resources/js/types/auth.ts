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
    /** Whether the Employees list holds more than the signed-in Employee. */
    canListEmployees: boolean;
    /** Whether the signed-in Employee may see any Work Entries, for My Work Entries. */
    canSeeWorkEntries: boolean;
    /** Whether the signed-in Employee may see Work Entries of anyone besides themselves, for the Work Entries page. */
    canSeeWorkEntryList: boolean;
    /** Whether the signed-in Employee may see Travel Expenses of anyone besides themselves, for the Travel Expenses page. */
    canSeeTravelExpenseList: boolean;
    /** Whether the signed-in Employee has Direct Reports, for the Team page. */
    canSeeTeam: boolean;
    /** Whether the signed-in Employee may open the Approvals page. */
    canSeeApprovals: boolean;
    /** How many Pending Work Entries and Time Off wait for the signed-in Employee to decide. */
    approvalsWaiting: number;
    /** Whether the signed-in Employee effectively manages anyone, for the Performance Evaluations page. */
    canSeePerformanceEvaluations: boolean;
};

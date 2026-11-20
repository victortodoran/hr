/** The signed-in Employee, as much as the user menu shows. */
export type Employee = {
    id: number;
    name: string;
    email: string;
    avatar?: string;
};

/** An area whose records the ScopeSwitch splits into the Employee's own and others' (rule L19). */
export type ScopedArea = 'work' | 'timeOff' | 'travel' | 'performance';

export type Auth = {
    employee: Employee;
    /** Company-wide Permissions the signed-in Employee holds. */
    can: string[];
    /** Whether the Employees list holds more than the signed-in Employee. */
    canListEmployees: boolean;
    /** Whether the signed-in Employee may see any Work Entries, for the Work page. */
    canSeeWorkEntries: boolean;
    /** Whether the signed-in Employee may see Work Entries of anyone besides themselves, for the Work Entries page. */
    canSeeWorkEntryList: boolean;
    /** Whether the signed-in Employee may see the Time Off of anyone besides themselves, for the Time Off page across Employees. */
    canSeeTimeOffList: boolean;
    /** Whether the signed-in Employee may see the travel of anyone besides themselves, for the Travel Requests page. */
    canSeeTravelRequestList: boolean;
    /** Whether the signed-in Employee may open the Directory: view directory, or Direct Reports for its Reports tab. */
    canSeeDirectory: boolean;
    /** Whether the signed-in Employee has Direct Reports, for the Team page. */
    canSeeTeam: boolean;
    /** Whether the signed-in Employee may open the Approvals page. */
    canSeeApprovals: boolean;
    /** How many Pending Work Entries and Time Off wait for the signed-in Employee to decide. */
    approvalsWaiting: number;
    /** Whether the signed-in Employee effectively manages anyone, for the Performance Evaluations page. */
    canSeePerformanceEvaluations: boolean;
    /** Per area, how far beyond themselves the signed-in Employee sees: everyone, reports, or nobody else (null). */
    othersReach: Record<ScopedArea, 'reports' | 'everyone' | null>;
};

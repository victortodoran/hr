export type Employee = {
    id: number;
    name: string;
    email: string;
    avatar?: string;
};

export type ScopedArea = 'work' | 'timeOff' | 'travel' | 'performance';

export type Auth = {
    employee: Employee;
    can: string[];
    canListEmployees: boolean;
    canSeeWorkEntries: boolean;
    canSeeWorkEntryList: boolean;
    canSeeTimeOffList: boolean;
    canSeeTravelRequestList: boolean;
    canSeeDirectory: boolean;
    canSeeTeam: boolean;
    canSeeApprovals: boolean;
    approvalsWaiting: number;
    canSeePerformanceEvaluations: boolean;
    /** Per area: everyone, reports, or nobody else (null). */
    othersReach: Record<ScopedArea, 'reports' | 'everyone' | null>;
};

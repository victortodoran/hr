import type { Choice } from './employee-record';

export type PerformanceEvaluationState = 'open' | 'completed';

export type Outcome = 'not-met' | 'partly-met' | 'met' | 'exceeded';

export type Progress = 'not-started' | 'on-track' | 'at-risk' | 'done';

export type OwnPerformanceEvaluation = {
    id: number;
    year: number;
    state: PerformanceEvaluationState;
    goals_count: number;
    at_risk_goals_count: number;
    outcome: Outcome | null;
};

export type PerformanceEvaluationView = {
    evaluation: PerformanceEvaluationRecord;
    isOwn: boolean;
    canEdit: boolean;
    canChangeProgress: boolean;
    canSeeOutcomes: boolean;
    canReopen: boolean;
};

export type PerformanceEvaluationRow = {
    id: number;
    name: string;
    evaluation: {
        id: number;
        state: PerformanceEvaluationState;
        goals_count: number;
        at_risk_goals_count: number;
        outcome: Outcome | null;
    };
};

export type CreatableYear = {
    year: number;
    employees: Choice[];
};

export type Goal = {
    id: number;
    title: string;
    description: string;
};

export type AssessedGoal = Goal & {
    progress: Progress;
    comments_count: number;
    outcome: Outcome | null;
};

export type GoalRecord = Goal & {
    progress: Progress;
    outcome: Outcome | null;
    given_at: string;
};

export type ProgressChange = {
    progress: Progress;
    previous_progress: Progress;
    changed_by: string;
    /** When it was changed, as an ISO 8601 date and time. */
    changed_at: string;
};

export type PerformanceEvaluationRecord = {
    id: number;
    year: number;
    state: PerformanceEvaluationState;
    employee: Choice;
    goals: AssessedGoal[];
    outcome: Outcome | null;
    summary: string | null;
    completed_at: string | null;
};

export type PerformanceEvaluationSummary = {
    id: number;
    year: number;
    employee: Choice;
};

export type GoalComment = {
    id: number;
    body: string;
    author: string;
    /** When it was written, as an ISO 8601 date and time. */
    written_at: string;
};

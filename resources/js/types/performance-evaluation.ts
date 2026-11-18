import type { Choice } from './employee-record';

/**
 * Where a Performance Evaluation stands.
 */
export type PerformanceEvaluationState = 'open' | 'completed';

/**
 * How well a Goal, or a Performance Evaluation as a whole, was achieved.
 */
export type Outcome = 'not-met' | 'partly-met' | 'met' | 'exceeded';

/**
 * One of the signed-in Employee's own Performance Evaluations on My
 * Performance Evaluations.
 */
export type OwnPerformanceEvaluation = {
    id: number;
    year: number;
    state: PerformanceEvaluationState;
    goals_count: number;
    /** The Outcome of the whole, once Completed. */
    outcome: Outcome | null;
};

/**
 * One Employee on the Performance Evaluations page, with their Performance
 * Evaluation for the year shown if they have one, and whether the viewer
 * may start it.
 */
export type PerformanceEvaluationRow = {
    id: number;
    name: string;
    evaluation: {
        id: number;
        state: PerformanceEvaluationState;
        goals_count: number;
        /** The Outcome of the whole, once Completed. */
        outcome: Outcome | null;
    } | null;
    can_start: boolean;
};

/**
 * Something the Employee's Manager asks them to achieve during the year.
 */
export type Goal = {
    id: number;
    title: string;
    description: string;
};

/**
 * A Goal on its Performance Evaluation's page, with its Outcome when the
 * viewer may see it and one has been given.
 */
export type AssessedGoal = Goal & {
    outcome: Outcome | null;
};

/**
 * A Performance Evaluation in full, with its Goals.
 */
export type PerformanceEvaluationRecord = {
    id: number;
    year: number;
    state: PerformanceEvaluationState;
    employee: Choice;
    goals: AssessedGoal[];
    /** The Outcome of the whole, when the viewer may see it and one has been given. */
    outcome: Outcome | null;
    /** The Manager's summary, when the viewer may see it and one has been written. */
    summary: string | null;
    /** When it was Completed, if it has been. */
    completed_at: string | null;
};

/**
 * What the Goal forms show of a Performance Evaluation: whose it is and
 * for which year.
 */
export type PerformanceEvaluationSummary = {
    id: number;
    year: number;
    employee: Choice;
};

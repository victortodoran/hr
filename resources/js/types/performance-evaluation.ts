import type { Choice } from './employee-record';

/**
 * Where a Performance Evaluation stands.
 */
export type PerformanceEvaluationState = 'open';

/**
 * One of the signed-in Employee's own Performance Evaluations on My
 * Performance Evaluations.
 */
export type OwnPerformanceEvaluation = {
    id: number;
    year: number;
    state: PerformanceEvaluationState;
    goals_count: number;
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
 * A Performance Evaluation in full, with its Goals.
 */
export type PerformanceEvaluationRecord = {
    id: number;
    year: number;
    state: PerformanceEvaluationState;
    employee: Choice;
    goals: Goal[];
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

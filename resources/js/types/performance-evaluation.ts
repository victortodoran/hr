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
 * How far along a Goal is during the year.
 */
export type Progress = 'not-started' | 'on-track' | 'at-risk' | 'done';

/**
 * One of the signed-in Employee's own Performance Evaluations on My
 * Performance Evaluations.
 */
export type OwnPerformanceEvaluation = {
    id: number;
    year: number;
    state: PerformanceEvaluationState;
    goals_count: number;
    /** How many of its Goals are At Risk now; always 0 once Completed. */
    at_risk_goals_count: number;
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
        /** How many of its Goals are At Risk now; always 0 once Completed. */
        at_risk_goals_count: number;
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
 * A Goal on its Performance Evaluation's page, with its Progress, and its
 * Outcome when the viewer may see it and one has been given.
 */
export type AssessedGoal = Goal & {
    progress: Progress;
    /** How many Comments it has, which go with it when it is removed. */
    comments_count: number;
    outcome: Outcome | null;
};

/**
 * A Goal on its own page, with its Progress and the day it was given.
 */
export type GoalRecord = Goal & {
    progress: Progress;
    given_at: string;
};

/**
 * One change to a Goal's Progress: to what, from what, by whom and when.
 */
export type ProgressChange = {
    progress: Progress;
    previous_progress: Progress;
    changed_by: string;
    /** When it was changed, as an ISO 8601 date and time. */
    changed_at: string;
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

/**
 * A short written message on a Goal, with its author and when it was
 * written.
 */
export type GoalComment = {
    id: number;
    body: string;
    author: string;
    /** When it was written, as an ISO 8601 date and time. */
    written_at: string;
};

import type { Choice } from './employee-record';

/**
 * One of an Employee's Assignments, as their Assignments page shows it.
 */
export type AssignmentRecord = {
    id: number;
    project: Choice & { client: string | null; is_internal: boolean };
    /** Only empty on the Internal Assignment, before an internal Job Title is set. */
    job_title: string | null;
    start_date: string;
    end_date: string | null;
    /** Only empty on the Internal Assignment, where it means the leftover time. */
    share: number | null;
};

/**
 * A Project an Employee can be put on, with the dates an Assignment must
 * fall within.
 */
export type OpenProject = Choice & {
    client: string | null;
    start_date: string;
    end_date: string | null;
};

/**
 * Someone's Assignment as a Project's team or a Client's page lists it.
 */
export type TeamAssignment = {
    id: number;
    employee: Choice;
    project?: Choice;
    job_title: string | null;
    start_date: string;
    end_date: string | null;
    share: number | null;
};

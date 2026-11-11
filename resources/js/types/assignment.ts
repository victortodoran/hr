import type { Choice } from './employee-record';

/**
 * One of an Employee's Assignments, as their Assignments page shows it.
 */
export type AssignmentRecord = {
    id: number;
    project: Choice & { client: string | null };
    job_title: string;
    start_date: string;
    end_date: string | null;
    share: number;
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
    job_title: string;
    start_date: string;
    end_date: string | null;
    share: number;
};

import type { Choice } from './employee-record';
import type { PayEntryRecord } from './pay';

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
    /** A whole percentage of the Employee's time, from 0 to 100. */
    capacity: number;
    /** The Pay history, newest first; only sent to viewers who may see it. */
    pay?: PayEntryRecord[];
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
 * One of a Client's Projects, with the Employees assigned to it today.
 */
export type ClientProject = Choice & {
    start_date: string;
    end_date: string | null;
    team: TeamAssignment[];
};

/**
 * Someone's Assignment as a Project's team or a Client's page lists it.
 */
export type TeamAssignment = {
    id: number;
    employee: Choice;
    job_title: string | null;
    start_date: string;
    end_date: string | null;
    capacity: number;
};

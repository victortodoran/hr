/**
 * How long an Employee worked on one Assignment on one date, and what
 * they did.
 */
export type WorkEntryRecord = {
    id: number;
    assignment_id: number;
    project: string;
    hours: number;
    description: string;
};

/**
 * One day of the week, with its Work Entries and their total hours.
 */
export type WorkEntryDay = {
    date: string;
    entries: WorkEntryRecord[];
    hours: number;
};

/**
 * The total hours on one Assignment in the week.
 */
export type WorkEntryAssignmentTotal = {
    assignment_id: number;
    project: string;
    hours: number;
};

/**
 * An Assignment hours can be recorded on, with the dates it runs.
 */
export type WorkEntryAssignment = {
    id: number;
    project: string;
    job_title: string | null;
    start_date: string;
    end_date: string | null;
};

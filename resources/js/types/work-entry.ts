/**
 * Where a Work Entry stands: only a Pending one can be changed.
 */
export type WorkEntryState = 'pending' | 'approved' | 'locked';

/**
 * Who approved or locked a Work Entry, and on which day.
 */
export type WorkEntrySignOff = {
    by: string;
    on: string;
};

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
    state: WorkEntryState;
    approved: WorkEntrySignOff | null;
    locked: WorkEntrySignOff | null;
    /** Whether the viewer may change or delete it. */
    can_change: boolean;
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

/**
 * A Work Entry on the Approvals page, with whose it is and whether the
 * viewer may approve it.
 */
export type ApprovalEntry = {
    id: number;
    date: string;
    employee: { id: number; name: string };
    project: string;
    hours: number;
    description: string;
    state: WorkEntryState;
    approved: WorkEntrySignOff | null;
    locked: WorkEntrySignOff | null;
    can_approve: boolean;
};

/**
 * The filters on the Approvals page; null means any.
 */
export type ApprovalFilters = {
    employee: number | null;
    project: number | null;
    from: string | null;
    to: string | null;
    state: WorkEntryState;
};

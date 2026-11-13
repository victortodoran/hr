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
    /** Whether the viewer may change it (and so delete it too). */
    can_edit: boolean;
    /** Whether the viewer may delete it. */
    can_delete: boolean;
};

/**
 * One day of the week, with its Work Entries and their total hours.
 */
export type WorkEntryDay = {
    date: string;
    entries: WorkEntryRecord[];
    hours: number;
    /** Whether Pending or Approved Time Off covers the weekday, and which kind; null when none does. */
    time_off: 'paid' | 'unpaid' | null;
    /** A past weekday while employed, with no Work Entries and no Time Off. */
    nothing_logged: boolean;
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
 * A Pending Work Entry waiting in the Approvals inbox, with whose it is.
 */
export type ApprovalEntry = {
    id: number;
    date: string;
    employee: { id: number; name: string };
    project: string;
    hours: number;
    description: string;
};

/**
 * A Work Entry on the Work Entries page, with whose it is, who signed it off
 * and whether the viewer may reopen or lock it.
 */
export type ListedWorkEntry = ApprovalEntry & {
    state: WorkEntryState;
    approved: WorkEntrySignOff | null;
    locked: WorkEntrySignOff | null;
    can_reopen: boolean;
    can_lock: boolean;
};

/** The tabs of the Approvals page. */
export type ApprovalsTab = 'work-entries' | 'time-off';

/** How many Pending Work Entries and Time Off wait for the viewer's decision. */
export type ApprovalsWaiting = {
    workEntries: number;
    timeOff: number;
};

/**
 * Filters on a list of Work Entries; null means any.
 */
export type WorkEntryFilters = {
    employee: number | null;
    project: number | null;
    from: string | null;
    to: string | null;
};

/**
 * The filters on the Work Entries page, which can also pick a state; null
 * means any.
 */
export type WorkEntryListFilters = WorkEntryFilters & {
    state: WorkEntryState | null;
};

/**
 * What locking the selected Work Entries would do: the Approved ones to be
 * locked, by Project and Employee, and the Pending ones matching the same
 * filters that won't be.
 */
export type LockSummary = {
    locking: { count: number; hours: number };
    breakdown: { project: string; employee: string; hours: number }[];
    pending: { count: number; hours: number };
};
